<?php

namespace app\plugin\digitalavatar\common;

use think\facade\Db;

class DigitalAvatarImageSkillService
{
    public static function list(int $aid, bool $activeOnly = false): array
    {
        self::ensureDefaults($aid);
        $query = Db::name('digitalavatar_image_skill')->where('aid', $aid);
        if ($activeOnly) {
            $query->where('status', 1);
        }
        $rows = $query->order('sort asc,id asc')->select()->toArray();
        return array_map([self::class, 'format'], $rows);
    }

    public static function portalList(int $aid): array
    {
        $list = [];
        foreach (self::list($aid, true) as $row) {
            $list[] = [
                'id' => intval($row['id']),
                'name' => strval($row['name']),
                'summary' => strval($row['summary']),
                'is_default' => intval($row['is_default']),
            ];
        }
        return $list;
    }

    public static function find(int $aid, int $id, bool $activeOnly = false): array
    {
        self::ensureDefaults($aid);
        if ($id <= 0) {
            return self::defaultSkill($aid, $activeOnly);
        }
        $query = Db::name('digitalavatar_image_skill')->where('aid', $aid)->where('id', $id);
        if ($activeOnly) {
            $query->where('status', 1);
        }
        $row = $query->find();
        if (!$row) {
            return self::defaultSkill($aid, $activeOnly);
        }
        return self::format($row);
    }

    public static function save(int $aid, array $info): array
    {
        $id = intval($info['id'] ?? 0);
        $name = trim(strval($info['name'] ?? ''));
        if ($name === '') {
            throw new \RuntimeException('请填写技能名称');
        }
        $content = trim(strval($info['content'] ?? ''));
        if ($content === '') {
            throw new \RuntimeException('请填写技能要求和流程');
        }

        $isDefault = intval($info['is_default'] ?? 0) === 1 ? 1 : 0;
        $data = [
            'aid' => $aid,
            'name' => mb_substr($name, 0, 80),
            'summary' => mb_substr(trim(strval($info['summary'] ?? '')), 0, 200),
            'content' => mb_substr($content, 0, 20000),
            'sort' => intval($info['sort'] ?? 0),
            'status' => intval($info['status'] ?? 1) === 1 ? 1 : 0,
            'is_default' => $isDefault,
            'update_time' => time(),
        ];

        if ($id > 0) {
            $exists = Db::name('digitalavatar_image_skill')->where('aid', $aid)->where('id', $id)->find();
            if (!$exists) {
                throw new \RuntimeException('技能不存在');
            }
            if (trim(strval($exists['skill_key'] ?? '')) !== '') {
                unset($data['skill_key']);
            }
            Db::name('digitalavatar_image_skill')->where('aid', $aid)->where('id', $id)->update($data);
        } else {
            $data['skill_key'] = self::uniqueSkillKey($aid, strval($info['skill_key'] ?? $name));
            $data['create_time'] = time();
            $id = intval(Db::name('digitalavatar_image_skill')->insertGetId($data));
        }

        if ($isDefault === 1) {
            Db::name('digitalavatar_image_skill')
                ->where('aid', $aid)
                ->where('id', '<>', $id)
                ->update(['is_default' => 0, 'update_time' => time()]);
        }

        return self::format(Db::name('digitalavatar_image_skill')->where('aid', $aid)->where('id', $id)->find() ?: []);
    }

    public static function delete(int $aid, int $id): void
    {
        if ($id <= 0) {
            throw new \RuntimeException('请传入技能ID');
        }
        $row = Db::name('digitalavatar_image_skill')->where('aid', $aid)->where('id', $id)->find();
        if (!$row) {
            throw new \RuntimeException('技能不存在');
        }
        Db::name('digitalavatar_image_skill')->where('aid', $aid)->where('id', $id)->delete();
        if (intval($row['is_default'] ?? 0) === 1) {
            $next = Db::name('digitalavatar_image_skill')
                ->where('aid', $aid)
                ->where('status', 1)
                ->order('sort asc,id asc')
                ->find();
            if ($next) {
                Db::name('digitalavatar_image_skill')
                    ->where('aid', $aid)
                    ->where('id', intval($next['id']))
                    ->update(['is_default' => 1, 'update_time' => time()]);
            }
        }
    }

    public static function ensureDefaults(int $aid): void
    {
        if ($aid < 0) {
            return;
        }
        try {
            $count = intval(Db::name('digitalavatar_image_skill')->where('aid', $aid)->count());
        } catch (\Throwable $e) {
            return;
        }

        self::dedupeBySkillKey($aid);

        $now = time();
        foreach (self::defaultDefinitions() as $index => $item) {
            $exists = Db::name('digitalavatar_image_skill')
                ->where('aid', $aid)
                ->where('skill_key', $item['skill_key'])
                ->find();
            if ($exists) {
                if ($item['skill_key'] === 'jersey-kit') {
                    Db::name('digitalavatar_image_skill')->where('id', intval($exists['id']))->update([
                        'name' => $item['name'],
                        'summary' => $item['summary'],
                        'content' => $item['content'],
                        'update_time' => $now,
                    ]);
                }
                continue;
            }
            try {
                Db::name('digitalavatar_image_skill')->insert([
                    'aid' => $aid,
                    'skill_key' => $item['skill_key'],
                    'name' => $item['name'],
                    'summary' => $item['summary'],
                    'content' => $item['content'],
                    'sort' => intval($item['sort'] ?? ($index + 1) * 10),
                    'status' => 1,
                    'is_default' => $count === 0 && intval($item['is_default'] ?? 0) === 1 ? 1 : 0,
                    'create_time' => $now,
                    'update_time' => $now,
                ]);
            } catch (\Throwable $e) {
                if (stripos($e->getMessage(), 'Duplicate') === false) {
                    throw $e;
                }
            }
        }

        $hasDefault = intval(Db::name('digitalavatar_image_skill')
            ->where('aid', $aid)
            ->where('status', 1)
            ->where('is_default', 1)
            ->count());
        if ($hasDefault <= 0) {
            $first = Db::name('digitalavatar_image_skill')
                ->where('aid', $aid)
                ->where('status', 1)
                ->order('sort asc,id asc')
                ->find();
            if ($first) {
                Db::name('digitalavatar_image_skill')
                    ->where('aid', $aid)
                    ->where('id', intval($first['id']))
                    ->update(['is_default' => 1, 'update_time' => $now]);
            }
        }
    }

    public static function dedupeBySkillKey(int $aid = -1): void
    {
        try {
            $query = Db::name('digitalavatar_image_skill');
            if ($aid >= 0) {
                $query->where('aid', $aid);
            }
            $rows = $query->order('id asc')->select()->toArray();
        } catch (\Throwable $e) {
            return;
        }
        $seen = [];
        foreach ($rows as $row) {
            $key = intval($row['aid'] ?? 0) . ':' . strval($row['skill_key'] ?? '');
            if ($key === '0:' && strval($row['skill_key'] ?? '') === '') {
                $key = intval($row['aid'] ?? 0) . ':id:' . intval($row['id'] ?? 0);
            }
            if (isset($seen[$key])) {
                Db::name('digitalavatar_image_skill')->where('id', intval($row['id']))->delete();
                continue;
            }
            $seen[$key] = true;
        }
    }

    private static function defaultSkill(int $aid, bool $activeOnly = false): array
    {
        $query = Db::name('digitalavatar_image_skill')->where('aid', $aid)->where('is_default', 1);
        if ($activeOnly) {
            $query->where('status', 1);
        }
        $row = $query->order('sort asc,id asc')->find();
        if (!$row && $activeOnly) {
            $row = Db::name('digitalavatar_image_skill')
                ->where('aid', $aid)
                ->where('status', 1)
                ->order('sort asc,id asc')
                ->find();
        }
        return $row ? self::format($row) : [];
    }

    private static function uniqueSkillKey(int $aid, string $source): string
    {
        $key = strtolower(trim($source));
        $key = preg_replace('/[^a-z0-9_\-]+/', '-', $key);
        $key = trim(strval($key), '-');
        if ($key === '') {
            $key = 'skill';
        }
        $key = mb_substr($key, 0, 50);
        $base = $key;
        $i = 2;
        while (Db::name('digitalavatar_image_skill')->where('aid', $aid)->where('skill_key', $key)->find()) {
            $key = mb_substr($base, 0, 46) . '-' . $i;
            $i++;
        }
        return $key;
    }

    private static function format(array $row): array
    {
        return [
            'id' => intval($row['id'] ?? 0),
            'skill_key' => strval($row['skill_key'] ?? ''),
            'name' => strval($row['name'] ?? ''),
            'summary' => strval($row['summary'] ?? ''),
            'content' => strval($row['content'] ?? ''),
            'sort' => intval($row['sort'] ?? 0),
            'status' => intval($row['status'] ?? 0),
            'is_default' => intval($row['is_default'] ?? 0),
            'create_time' => intval($row['create_time'] ?? 0),
            'update_time' => intval($row['update_time'] ?? 0),
        ];
    }

    private static function defaultDefinitions(): array
    {
        return [
            [
                'skill_key' => 'general',
                'name' => '通用作图',
                'summary' => '商品、人物、场景、海报都能用。先理解需求，再决定问一句还是出图。',
                'sort' => 10,
                'is_default' => 1,
                'content' => implode("\n", [
                    '角色：你是作图智能体，负责把用户的一句话变成可执行的出图方案。',
                    '目标：能直接出图就出图；只有首轮极度含糊才先问一句。',
                    '',
                    '要求：',
                    '1. 用户已说出主体、场景、风格或明确修改指令时，必须出图。',
                    '2. 提示词补全构图、光影、材质、镜头、风格和不要出现的内容，具体可执行，不要只复述原话。',
                    '3. 用户用「图1」「图2」指代图库。只有一张时就是图1，不要主动提「参考图」，也不要假设一定有图2。',
                    '4. 已有底图或用户说改、换成、更暗、去掉某物时，按局部修改处理：写清保留什么、改什么。',
                    '5. 涂鸦笔迹只表示修改位置，成品里不要画出笔迹。',
                    '6. 回复像设计师助手，口语、干脆，不要自称 AI。',
                    '',
                    '流程：',
                    '1. 识别用户提到的图编号、当前画布、圈选和批注。',
                    '2. 判断是新图还是在已有图上改。',
                    '3. 信息足够则生成完整提示词并出图；不够则只问一个关键问题，并给 2-3 个可选方向。',
                    '4. 出图后等用户继续改，不要再教用户写「图1参考图2」。',
                ]),
            ],
            [
                'skill_key' => 'jersey-kit',
                'name' => '球衣套装拆图',
                'summary' => '按设计图拆 6 张：上衣正背、短裤正背用 2D 平面稿；整套正背用 3D 全身。F-004 领型，领口罗纹颜色跟设计稿，正面灰领内侧无黑线，背面只见同色罗纹不见灰领。去字去标，胸条不绕袖，短裤无褶皱。',
                'sort' => 40,
                'is_default' => 0,
                'content' => implode("\n", [
                    '角色：把用户的球衣设计图拆成可定制展示图。你只组织工具和锁定提示词，不要另写工具链。',
                    '',
                    '产出 6 张独立 PNG，顺序固定，禁止六宫格、禁止拼一张：',
                    '1 上衣正面 2D → 2 上衣背面 2D → 3 短裤正面 2D → 4 短裤背面 2D → 5 整套正面 3D → 6 整套背面 3D',
                    '',
                    '参考图：用户说的「图N」或刚上传的设计图。没有图就 ask_user。不要默认抓图1。',
                    '规格：每次 generate_image 都是 count=1、size=2000x2000、background=transparent、stage=draft、figure=设计图。禁止一次 count=6。不要自动转高清。',
                    '',
                    '流程：',
                    '- 首次有设计图：按 1-6 连续 6 次 generate_image，每次只用对应锁定提示词。',
                    '- 用户说「下一个 / 可以 / 对了」：只出下一张，不要重出已确认的。',
                    '- 用户圈错：只重出被指出的那一张，2D/3D 规则不要跟错。',
                    '',
                    '通用：去掉所有文字、数字、Logo、队徽、号码、球员名。颜色、版型、暗纹、滚边只跟参考图，不发明新色块。',
                    '色块位置：胸口/衣身色块只在衣身，默认不绕到袖子。袖子保持衣身底色+暗纹；对比色只出现在参考图有的位置（通常是领口、袖口、插肩滚边、侧条）。参考图背面没有胸条，背面就不要画胸条。',
                    '袜子球鞋：设计图通常没有。整套用白长袜+一圈队服强调色翻边、黑色无品牌足球鞋。',
                    '',
                    '======== 领型锁定 F-004（1/2/5/6 整段照抄，禁止改写成圆领/polo）========',
                    'COLLAR LOCK F-004. Front and back are DIFFERENT. Never copy the front collar onto a back view.',
                    'COLLAR RIB COLOR (critical): the outer F-004 rib color MUST copy the design sheet collar, 1:1. If the design collar is yellow / gold / orange / teal / any contrast color, ALL four views (1, 2, 5, 6) use THAT color. Do NOT default the rib to white. White rib is allowed ONLY when the design collar itself is white or pale-white. Cuffs, raglan piping, and collar are independent — match each trim to the design, do not copy cuff color onto a white design collar or paint a yellow design collar white.',
                    'FRONT collar (views 1 and 5): closed standing F-004, not a bound crew. Outer rim is a thick rib in the DESIGN collar color (yellow if the design is yellow, white only if the design is white). The rib is TWO THICK SIDE WINGS — much thicker/taller on the left and right, thinner at the bottom center. Inner neck is a GRAY filled FACE: a shallow WIDE rounded-rectangle panel (wider than tall), like the inside lining of a standing collar, NOT a deep round U hole, not empty, not see-through. Top edge of the collar is nearly flat / slightly curved, not a round arch. The front center of the rib tucks inward. Not an even-thickness white ring around a circular neck hole, not V-neck, not polo, not mandarin.',
                    'INNER COLLAR CLEAN (critical): the gray inner face is smooth uniform gray. NO black line, NO black stroke, NO black outline, NO dark rim, NO black seam, NO black stitching where the rib meets the gray face. Do not draw any black or dark hairline inside the collar. The inner crease of the rib may be the rib color itself, never a black hairline.',
                    'BACK collar (views 2 and 6): rear view only. From behind you see ONLY a solid opaque rib band in the SAME design collar color as the front, sitting on the neck. Do NOT show the front collar. Do NOT show the gray inner face. Do NOT show a neck hole. Do NOT look through the collar. Not polo from behind. If the front rib is yellow, the back rib is yellow too — never leave the back rib white.',
                    '',
                    '======== 1-4 单件｜必须 2D，整段复制，只改最后 VIEW ========',
                    '2D soccer-kit designer technical flat. One garment only, centered, complete, not cropped. Transparent background PNG. Flat vector color fills, clean custom-jersey catalog display.',
                    'SHARP 2D (critical): crisp hard-edge vector fills. Geometric triangles / confetti / mosaic must have sharp vertices and clean edges. No blur, no painterly smudge, no glow, no soft anti-alias halo on the collar or prints.',
                    'NOT a 3D product CAD, NOT photoreal fabric, NOT a fitted athletic raglan t-shirt, NOT a mannequin, hanger, or person. No studio shadow, no waist taper.',
                    'Copy the reference 1:1: silhouette, sleeve type, sleeve length, raglan/set-in seams, side panels, hems, stitching, pattern scale and pattern placement. Collar follows COLLAR LOCK F-004 above, not a generic crew.',
                    'Silhouette: boxy rectangular body, short T-shaped soccer-kit sleeves. Keep the 2D kit-template sleeve, not a 3D performance raglan.',
                    'COLOR PLACEMENT: Copy colors from the reference. Do not invent new color-blocks. Torso color-blocks stay on the TORSO only. Do NOT wrap a chest/torso color-block onto the sleeves unless the reference sleeve itself is that color. Sleeves keep the same base color and repeating pattern as the reference sleeves. Contrast on sleeves only as thin cuff and thin raglan piping when the reference has them. Cuff, raglan piping, and side panels follow the reference trims only.',
                    'BODY COLOR MATCH (critical): the jersey FRONT and BACK must use the SAME body fill as the design — same hue, same lightness, same saturation. Do not paint the front washed-out/lighter and the back darker, or vice versa.',
                    'PATTERN PLACEMENT (critical): copy the design print 1:1. If the design has BROKEN / OFFSET / INTERRUPTED diagonal bars of different lengths, keep them broken. Do NOT convert them into evenly spaced continuous stripes wrapping from sleeve onto torso across the raglan. Each bar must match the design position, length, angle, and width. Sleeves only get the marks the design sleeve actually has.',
                    'SLEEVE PRINTS (critical): if the 2D FRONT sleeve has bars/marks and the 2D BACK sleeve is plain, those marks sit on the FRONT FACE of the sleeve only, in the same place as the 2D (often near the sleeve hem). They must NOT wrap 360 around the arm, must NOT sit high on the deltoid, and must NOT appear on the back of the sleeve in 3D front or back views.',
                    'REMOVE all text, letters, numbers, logos, crests, wordmarks, player names. Clean apparel only.',
                    '1 VIEW: soccer jersey FRONT only. Chest color-block on torso only; sleeves keep body base pattern plus cuff and raglan piping. FRONT F-004 collar: outer rib in the DESIGN collar color + gray filled inner face, closed inward tuck. Smooth gray inside, no black inner line. Do not paint a yellow/colored design collar white.',
                    '2 VIEW: soccer jersey BACK only. Same body, sleeves, piping as the front. No chest color-block on the back unless the reference back has it. BACK collar only: solid opaque rib band in the SAME design collar color as the front. Do not draw the front collar, gray inner face, or a neck hole. Do not leave a white back rib when the design collar is yellow or any other contrast color.',
                    '3 VIEW: soccer shorts FRONT only. Perfectly flat vector shorts. ZERO wrinkles, fold lines, gathering strokes, or draping. Allowed lines only: outer outline, waistband edges, one center fly stitch. Outline and stitch color must match the 2D jersey (dark/black cartoon stroke), never white. Copy length, hem curve, and any real side stripes from the reference. Remove numbers.',
                    '4 VIEW: soccer shorts BACK only. Same flat rule and fit as the front shorts. Allowed lines only: outer outline, waistband edges, one center back stitch. SAME outline and stitch COLOR as the front shorts and the 2D jersey — dark/black cartoon stroke. Do NOT switch to white lines on the back. SHORTS SIZE MATCH (critical): front and back shorts must be the SAME size on the 1024 canvas — same width, same length, same waistband height, same hem curve, same margins. Do NOT draw the back wider, taller, or smaller than the front. No numbers.',
                    '',
                    '======== 5-6 整套｜必须实际 3D，禁止 2D 平铺 ========',
                    'Photoreal 3D visualization of a complete soccer kit as worn. Invisible ghost mannequin: no head, no face, no skin, no person identity. Jersey + shorts + socks + football boots assembled in wearing position, like a 3D e-commerce ghost-mannequin sports kit.',
                    'SLIM ATHLETIC BODY (critical): the ghost mannequin is a slim athletic soccer player. NOT chubby, NOT a wide barrel torso, NOT balloon/baggy shorts, NOT fat calves or thick socks. Slim shins, moderate soccer-short width, athletic shoulders. FRONT 3D and BACK 3D must use the SAME slim body width.',
                    'Realistic athletic fabric, subtle cloth volume, studio lighting. Centered, complete, not cropped. Transparent background.',
                    'NOT a 2D kit template, NOT a flat technical drawing, NOT a comic, NOT paper cutouts laid on a table, NOT a visible dummy head.',
                    'Keep the same colors and construction as the 2D pieces: jersey pattern and trims from the reference, chest color-block on TORSO only (do not wrap onto sleeves), shorts without numbers. Socks: white knee-high with a thin cuff in the kit accent color — the cuff color must match on FRONT and BACK. SOCK HEIGHT MATCH (critical): FRONT 3D (view 5) and BACK 3D (view 6) must use the SAME sock construction. Pull the white socks UP under the shorts so they COVER the knees on BOTH views. Do NOT leave an empty gap between shorts hem and sock top on one view while the other has socks tucked under the shorts. Same sock height, same knee coverage, same thin accent-cuff position on both views. Boots: black unbranded football cleats, no logos, no Nike swoosh. FRONT and BACK boots must be the SAME pair: both black, never navy/blue/colored on one side and black on the other.',
                    'REMOVE all text, letters, numbers, logos, crests, wordmarks, player names.',
                    '5 VIEW: full kit FRONT, 3D ghost mannequin, wearing pose, camera on the chest. Same FRONT F-004 collar as view 1: outer rib in the DESIGN collar color, gray filled inner face, closed inward tuck. If the 2D jersey collar is yellow, this 3D collar rib is yellow too — never white. Inner gray is smooth uniform gray — no black inner line, no dark rim, no black seam inside the collar. Socks pulled UP under the shorts covering the knees — no empty gap at the knee. Boots: black unbranded, same as view 6.',
                    '6 VIEW: full kit BACK, 3D ghost mannequin, wearing pose, camera on the back. Back jersey has no chest color-block unless the reference back has it. BACK collar only: solid opaque rib in the SAME design collar color as views 1/2/5. Do not show the front collar or gray inner face. If the front rib is yellow, the back rib is yellow. Socks: same white knee-high pulled under the shorts, same knee coverage and same accent cuff as view 5. Boots: black unbranded football cleats from behind, identical to view 5 — NOT navy, NOT branded.',
                    '',
                    '禁止：1-4 画成 3D；5-6 画成 2D 平铺；把插肩袖画成立体运动衫；把胸口色块绕到袖子上；短裤加褶皱；加字加号加队徽；把正面 F-004 灰领画到背面；圆领开孔；领口内侧画黑线/黑描边；设计稿领口是黄/橙/其他对比色时画成白领；正反面领口罗纹颜色不一致；正反面球鞋颜色/品牌不一致；背面改成藏青或带勾球鞋；把设计稿上断开/错位的斜条画成通体连续斜纹；把只印在袖子正面的条纹绕到袖背或画到 3D 背面；同一件正反面描边/缝线颜色不一致（例如正面黑线、背面白线）；同一件上衣正反面身色深浅不一致；正面 3D 膝盖空一截、背面袜子却拉到短裤里（正反面袜子高度/膝盖覆盖不一致）；同一条短裤正反面大小/宽度不一致；2D 三角/马赛克/条纹画糊画软；3D 人体画胖、短裤画成灯笼、小腿袜子画粗。',
                    '回复：短说现在出第几张、2D 还是 3D。不要教用户写提示词。',
                ]),
            ],
            [
                'skill_key' => 'logo',
                'name' => 'Logo 标志',
                'summary' => '做简洁图形标志。少文字、可缩放、适合品牌识别。',
                'sort' => 20,
                'is_default' => 0,
                'content' => implode("\n", [
                    '角色：你专门做 Logo / 标志图形。',
                    '目标：输出识别度高、可缩放、适合做品牌标志的图形，而不是风景海报。',
                    '',
                    '要求：',
                    '1. 默认简洁矢量感、中心构图、留白干净、少装饰、少透视。',
                    '2. 除非用户明确要求，不要大段中文、不要复杂场景、不要照片级人物。',
                    '3. 有底图时优先沿用已有图形语言再改，不要整张换风格。',
                    '4. 提示词写清图形主体、配色、线面关系、背景是否透明或纯色。',
                    '',
                    '流程：',
                    '1. 确认品牌名/图形主体、行业气质、配色。',
                    '2. 信息足够则直接出图；缺关键信息时只问一个问题。',
                    '3. 后续修改只改指定部分，保持标志识别度。',
                ]),
            ],
            [
                'skill_key' => 'local-edit',
                'name' => '局部修改',
                'summary' => '按图1和圈选位置改局部，其余尽量保持不变。',
                'sort' => 30,
                'is_default' => 0,
                'content' => implode("\n", [
                    '角色：你专门基于已有图片做局部修改。',
                    '目标：只改用户指定的位置和内容，其余构图、主体、光影尽量保持不变。',
                    '',
                    '要求：',
                    '1. 必须使用用户提供的底图，不要另起一张无关新图。',
                    '2. 用户提到图1、图2时，按编号使用对应图片。',
                    '3. 彩色笔迹、圈选、批注只表示修改位置，成品不要画出笔迹。',
                    '4. 提示词必须写清：保留什么、改什么、改到什么程度。',
                    '5. 没有底图时，先让用户点图库或上传一张图，不要空改。',
                    '',
                    '流程：',
                    '1. 锁定当前画布或用户提到的图作为底图。',
                    '2. 提取圈选、批注和文字修改指令。',
                    '3. 生成「基于底图局部修改」的提示词并出图。',
                    '4. 回复说明改了哪一块，方便用户继续微调。',
                ]),
            ],
        ];
    }
}

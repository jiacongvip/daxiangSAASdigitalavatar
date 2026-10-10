<?php
// JK客户定制

namespace app\admin\model;
use think\Model;
use think\facade\Db;
use app\common\Wechat;
class Member extends Model
{
    public static function validateTelUnique($aid, $tel, $excludeMemberId = 0)
    {
        $tel = trim((string)$tel);
        if ($tel === '') {
            return ['status' => 1, 'msg' => '', 'tel' => ''];
        }

        $query = Db::name('member')
            ->where('aid', intval($aid))
            ->where('tel', $tel);
        if (intval($excludeMemberId) > 0) {
            $query->where('id', '<>', intval($excludeMemberId));
        }

        if ($query->field('id')->find()) {
            return ['status' => 0, 'msg' => '该手机号已被其他会员使用', 'tel' => $tel];
        }

        return ['status' => 1, 'msg' => '', 'tel' => $tel];
    }

    protected static function normalizeNullableIdentityFields(array &$data)
    {
        $nullableFields = [
            'wxopenid',
            'mpopenid',
            'appopenid',
            'unionid',
            'alipayopenid',
            'baiduopenid',
            'qqopenid',
            'toutiaoopenid',
            'iosopenid',
            'googleopenid',
            'alipay_openid',
        ];

        foreach ($nullableFields as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }
    }

	//添加会员
	function add($aid,$data){
		\think\facade\Log::write('=== Member模型::add调用 ===');
		\think\facade\Log::write('Member::add参数: aid='.$aid.', data='.json_encode($data));
		\think\facade\Log::write('Member::add调用堆栈: '.json_encode(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 8)));

		self::normalizeNullableIdentityFields($data);
		$data['aid'] = intval($aid);
		
		if(empty($data['levelid'])){
			$data['levelid'] = Db::name('member_level')->where('aid',$aid)->where('isdefault',1)->value('id');
			if (empty($data['levelid'])) {
				$data['levelid'] = Db::name('member_level')->where('aid', $aid)->order('id asc')->value('id');
			}
			$data['levelid'] = intval($data['levelid'] ?: 0);
		}
        if(empty($data['pid'])){
            $sessionid = \think\facade\Session::getId();
            }

		if(isset($data['pid']) && $data['pid']){
			$parent = Db::name('member')->where('aid',$aid)->where('id',$data['pid'])->find();
			if(!$parent){
				$data['pid'] = 0;
			}else{
				if($parent['path']){
					$data['path'] = $parent['path'] . ',' .$parent['id'];
				}else{
					$data['path'] = ''.$parent['id'];
				}
                }
		}else{
			$data['path'] = '';
		}
		if(getcustom('member_overdraft_money')){
            $admin = Db::name('admin')->where('id',$aid)->find();
            if($admin['overdraft_init']>0){
                $data['overdraft_money'] = $admin['overdraft_init'];
            }
        }
        if (!isset($data['headimg']) || trim((string)$data['headimg']) === '') {
            $data['headimg'] = \app\admin\common\Member::getDefaultAvatar($aid);
        }
		 // 👇 以下为【字段默认值统一处理】：按字段逐个判断赋值，保持一行简洁写法
		 !isset($data['total_fenhong'])           && $data['total_fenhong']           = 0;
		 !isset($data['total_fenhong_team'])      && $data['total_fenhong_team']      = 0;
		 !isset($data['total_fenhong_level_team']) && $data['total_fenhong_level_team'] = 0;
		 !isset($data['total_fenhong_partner'])   && $data['total_fenhong_partner']   = 0;
		 !isset($data['total_fenhong_area'])      && $data['total_fenhong_area']      = 0;
		 !isset($data['scoreA'])                  && $data['scoreA']                  = 0;
		 !isset($data['scoreB'])                  && $data['scoreB']                  = 0;
		 !isset($data['duihuanfen'])              && $data['duihuanfen']              = 0;
		 !isset($data['cyz'])                     && $data['cyz']                     = 0;
		 !isset($data['heiscore'])                && $data['heiscore']                = 0;
		 !isset($data['xiaofeib'])                && $data['xiaofeib']                = 0;
		 !isset($data['suo1jiang'])               && $data['suo1jiang']               = 0;
		 !isset($data['suo2jiang'])               && $data['suo2jiang']               = 0;
		 !isset($data['jiangcount'])              && $data['jiangcount']              = 0;
		 !isset($data['zijifh'])                  && $data['zijifh']                  = 0;
		 !isset($data['fxfhc'])                   && $data['fxfhc']                   = 0;
		 !isset($data['syc'])                     && $data['syc']                     = 0;
		 !isset($data['score3'])                  && $data['score3']                  = 0;
		 !isset($data['tuozhanfei'])              && $data['tuozhanfei']              = 0;
		 !isset($data['temp_frozen_money'])       && $data['temp_frozen_money']       = 0;
		 !isset($data['scorexiaofeizhi'])         && $data['scorexiaofeizhi']         = 0;
		 !isset($data['scorelv'])                 && $data['scorelv']                 = 0;
		 !isset($data['score2'])                  && $data['score2']                  = 0;
		 !isset($data['score'])                   && $data['score']                   = 0;
		 !isset($data['money'])                   && $data['money']                   = 0;
		 !isset($data['s_money'])                 && $data['s_money']                 = '';
		 !isset($data['totalcommission2'])        && $data['totalcommission2']        = 0;
		 !isset($data['totalcommission'])         && $data['totalcommission']         = 0;
		 !isset($data['commission2'])             && $data['commission2']             = 0;
		 !isset($data['commission'])              && $data['commission']              = 0;
		 !isset($data['bus_total'])               && $data['bus_total']               = 0;
		 !isset($data['fenhong_num'])             && $data['fenhong_num']             = 0;
		 !isset($data['contribution_num'])        && $data['contribution_num']        = 0;
		 !isset($data['guquan'])                  && $data['guquan']                  = 0;
		 !isset($data['fanxian_nums'])            && $data['fanxian_nums']            = 0;
		 !isset($data['scorehuang'])              && $data['scorehuang']              = 0;
	


		$data['random_str'] = random(16);
		$set = Db::name('admin_set')->where('aid',$aid)->find();
		if($set['reg_invite_code'] != 0 && $set['reg_invite_code_type']==1){
			$data['yqcode'] = self::getyqcode($aid);
		}
		if($set['reg_check'] == 1) $data['checkst'] = 0;
		$data['iscountscore'] = 1;
		
		$member = self::create($data);
        return $member->id;
	}
    //生成邀请码，目前仅6位数字格式
	function getyqcode($aid){
		$code = rand(100000,999999);
		$member = Db::name('member')->where('aid',$aid)->where('yqcode',$code)->find();
		if($member){
			return self::getyqcode($aid);
		}
		return $code;
	}
	//修改会员
	function edit($aid,$data){
        self::normalizeNullableIdentityFields($data);

		$member = self::where('aid',$aid)->where('id',$data['id'])->find();
		if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];
		if(array_key_exists('tel', $data)){
			$telCheck = self::validateTelUnique($aid, $data['tel'], $member['id']);
			if($telCheck['status'] == 0) return $telCheck;
			$data['tel'] = $telCheck['tel'];
		}
		$oldpath = $member['path'];
        $oldlevelid = $member['levelid'];

		// 接点人变更/推荐人变更时：校验线下归属 + 防死循环
		$jiedianChanged = array_key_exists('jiedianpid', $data) && intval($data['jiedianpid']) != intval($member['jiedianpid']);
		$pidChanged = array_key_exists('pid', $data) && intval($data['pid']) != intval($member['pid']);
		if($jiedianChanged || $pidChanged){
			$effectiveJiedianpid = array_key_exists('jiedianpid', $data) ? intval($data['jiedianpid']) : intval($member['jiedianpid']);
			$effectivePid = array_key_exists('pid', $data) ? intval($data['pid']) : intval($member['pid']);
			if($effectiveJiedianpid > 0){
				$jiedianCheck = \app\admin\common\MemberRelationService::validateJiedianAssign(
					$aid,
					intval($member['id']),
					$effectiveJiedianpid,
					$effectivePid
				);
				if(($jiedianCheck['status'] ?? 0) != 1){
					return ['status'=>0,'msg'=>$jiedianCheck['msg'] ?? '接点人设置不合法'];
				}
			}
		}

		if(isset($data['pid'])){
            if($data['pid'] > 0){
                if($data['pid'] == $member['id']) return ['status'=>0,'msg'=>'不能设置自己为自己的上级'];
                $parent = Db::name('member')->where('aid',$aid)->where('id',$data['pid'])->find();
                if($parent['pid'] == $member['id']) return ['status'=>0,'msg'=>'不能互为上下级关系'];
                $parent_pids = explode(',',$parent['path']);
                if(in_array($data['id'],$parent_pids)){
                    return ['status'=>0,'msg'=>'不能以自己的网体会员作为上级'];
                }
            }
			if(!$parent){
				$data['pid'] = 0;
                $data['path'] = '';
			}else{
				if($parent['path']){
					$data['path'] = $parent['path'] . ',' .$parent['id'];
				}else{
					$data['path'] = ''.$parent['id'];
				}
			}
		}else{
			$data['path'] = '';
		}
        $data['path'] = ltrim($data['path'],',');
		if(!$data['aid']) $data['aid'] = $aid;
		if(!$data['random_str']) $data['random_str'] = random(16);
		$member->save($data);
		if($data['path'] != $oldpath){
			$allpath = trim($oldpath.','.$data['path'].','.$member->id);
//			self::updatepath_bak($allpath);
            self::updatepath($member->id,$data['path']);
		}
		\app\admin\common\Wechat::updatemembercard($aid,$data['id']);
		 return ['status'=>1,'msg'=>'修改成功'];
	}
	//删除会员
	function del($aid,$mids,$bid = 0){
		if(!$mids) return ['status'=>0,'msg'=>'没有要删除的会员'];
		$mids = array_values(array_unique(array_filter(array_map('intval', (array)$mids))));
		if(!$mids) return ['status'=>0,'msg'=>'没有要删除的会员'];
		$where = [];
		$where[] = ['aid','=',aid];
        $where[] = ['id','in',$mids];
        $memberlist = self::where($where)->select();
		foreach($memberlist as $member){
			$sessionIds = Db::name('session')->where('aid',aid)->where('mid',$member['id'])->column('session_id');
			if(!empty($sessionIds)){
				foreach($sessionIds as $sessionId){
					if($sessionId){
						cache($sessionId.'_mid', null);
					}
				}
				Db::name('session')->where('aid',aid)->where('mid',$member['id'])->delete();
			}
			if(isset($member['session_id']) && $member['session_id']){
				cache($member['session_id'].'_mid', null);
			}
			$downlist = self::where('aid',aid)->where('find_in_set('.$member['id'].',path)')->select();
			foreach($downlist as $down){
				if($down['pid'] == $member['id']){
					$down->pid = $member['pid'];
					$down->path = $member['path'];
				}else{
					$pathArr = explode(',',$down['path']);
					foreach($pathArr as $k=>$v){
						if($v == $member['id']){
							unset($pathArr[$k]);
						}
					}
					$down->path = implode(',',$pathArr);
				}
				$down->save();
			}
			self::where('aid',aid)->where('id',$member['id'])->delete();
            Db::name('member_level_record')->where('aid',aid)->where('mid',$member['id'])->delete();
            Db::name('member_favorite')->where('aid',aid)->where('mid',$member['id'])->delete();
            Db::name('member_address')->where('aid',aid)->where('mid',$member['id'])->delete();
            Db::name('member_history')->where('aid',aid)->where('mid',$member['id'])->delete();
            Db::name('member_poster')->where('aid',aid)->where('mid',$member['id'])->delete();
            Db::name('kefu_message')->where('aid',aid)->where('mid',$member['id'])->delete();
		}
		return ['status'=>1,'msg'=>'删除成功'];
	}

    //会员path
    function updatepath($pid,$new_path=''){
        // 验证pid参数，确保是有效的数字ID
        if (!is_numeric($pid) || $pid <= 0) {
            \think\facade\Log::error('updatepath方法接收到无效的pid参数: ' . var_export($pid, true));
            return false;
        }
        
        // 确保pid是整数
        $pid = intval($pid);
        
        if($new_path){
            //处理path异常问题
            $patharr = explode(',',$new_path);
            $patharr = array_filter($patharr);
            $patharr = array_unique($patharr);
            // 确保path中所有元素都是数字
            $patharr = array_filter($patharr, function($item) {
                return is_numeric($item) && $item > 0;
            });
            $new_path = implode(',',$patharr);
        }
        $memberlist = Db::name('member')->where("find_in_set(?,path)", [$pid])->field('id,pid,path')->order('id')->select()->toArray();
        //按path长度排序，path越长层级越大
        usort($memberlist,function($a,$b){
            $al = count(explode(',',$a['path']));
            $bl = count(explode(',',$b['path']));
            if ($al == $bl)
                return 0;
            return ($al > $bl) ? 1 : -1;
        });
        $path_arr = [];
        $path_arr[$pid] = $new_path;
        foreach($memberlist as $member){
            $parent_path = $path_arr[$member['pid']];
            if($parent_path){
                $path = $parent_path.','.$member['pid'];
            }else{
                $path = $member['pid'];
            }
            $path = ltrim($path,',');
            Db::name('member')->where('id',$member['id'])->update(['path'=>$path]);
            $path_arr[$member['id']] = $path;
        }
    }
	
	//会员path 废弃
	function updatepath_bak($pathstr){
		if(!$pathstr) return;
		$pathArr = explode(',',$pathstr);
        $pathArr = array_unique($pathArr);
		foreach($pathArr as $pid){
			if(!$pid) continue;
			$memberlist = Db::name('member')->where("find_in_set(?,path)", [$pid])->order('id')->select()->toArray();
			foreach($memberlist as $member){
				$pathArr = self::getpath($member,$path=[]);
				if($pathArr){
					$pathArr = array_reverse($pathArr);
					$pathstr = implode(',',$pathArr);
					Db::name('member')->where('id',$member['id'])->update(['path'=>$pathstr]);
				}else{
					Db::name('member')->where('id',$member['id'])->update(['path'=>'']);
				}
			}
		}
	}
	function getpath($member,$path,$deep=1){
		if($member['pid'] && $deep < 20){
			if(in_array($member['pid'],$path)) return $path;
			$path[] = $member['pid'];
			$parent = Db::name('member')->where('id',$member['pid'])->find();
			$deep = $deep +1;
			return self::getpath($parent,$path,$deep);
		}
		return $path;
	}
	public function send_free_notice($data){

    	}


	//会员打标签
	function member_tag(){
		//查询标签
		$taglist = Db('member_tag')->where('aid',aid)->where('status',1)->where('type',1)->select()->toArray();
		foreach($taglist as $t){
			//$where = [];
			$where = '';
			if($t['regdatestatus']==1){
				$starttime = time() - $t['maxdays']*86400;
				$endtime = time() - $t['mindays']*86400;
				
				$where .= '  createtime between '.$starttime.' and '.$endtime;
			}

			if($t['levelstatus']==1){
				$where.= ' '.($t['regdatestatus']==1?$t['condition']:'').' levelid = '.$t['levelid'];
			}
	
			if($t['buystatus']==1){
				$where.= ' '.(($t['regdatestatus']==1 || $t['levelstatus']==1)?$t['condition']:'').' buynum  >='.$t['buynum'];
			}
			if($t['buymoneystatus']==1){
				$where.= ' '.(($t['regdatestatus']==1 || $t['levelstatus']==1 || $t['buystatus']==1)?$t['condition']:'').' buymoney >='.$t['buymoney'];
			}
			$memberlist = Db('member')->where('aid',aid)->where('checkst',1)->where($where)->column('id');
			//查订单表
			if($t['prostatus']==1){
				$proids = explode(',',$t['productids']);
				$mids = Db::name('member')->alias('m')->leftJoin('shop_order_goods og','m.id=og.mid')->where('og.aid',aid)->where('proid','in',$proids)->where('og.status','in','1,2,3')->group('og.mid')->column('og.mid');
				if($t['condition']=='or'){
					foreach($mids as $m){
						if(!in_array($m,$memberlist)){
							$memberlist[] = $m;
						}
					}
				}
				if($t['condition']=='and'){
					foreach($memberlist as $m){
						if(!in_array($m,$mids)){
							unset($m);
						}
					}
				}
			}
			foreach($memberlist as $m2){
				$member = Db::name('member')->where('aid', aid)->where('id', $m2)->find();
				$mtags = $t['id'];
				if($member['tags']){
					$tagarr = explode(',',$member['tags']);
					if(!in_array($t['id'],$tagarr)){
						$mtags = $member['tags'].','.$t['id'];
						Db::name('member')->where('aid', aid)->where('id', $member['id'])->update(['tags' => $mtags]);
					}
				}else{
					Db::name('member')->where('aid', aid)->where('id', $member['id'])->update(['tags' => $mtags]);
				}
			}
		}
	}
}

import{c as E}from"./clientauth-Ds4utaRJ.js";import{u as G,_ as I}from"./index-CTO9TwcJ.js";import{h as O}from"./antd-vendor-BXeG7MAg.js";import{d as V,f as J,e as s,o as K,V as d,a1 as _,X as p,k as t,Y as a,$ as o,a4 as f,F as L,a2 as D,G as v}from"./vue-vendor-BH3QE-KO.js";import"./utils-vendor-dR2luS_Z.js";import"./charts-vendor-CwCbZZ1s.js";const F={class:"guide-page"},M={class:"meta-grid"},H={class:"mono"},q={class:"mono"},U={key:0,class:"product-list"},X={key:1,class:"sub"},Y=V({__name:"integration-guide",setup(Q){const C=G(),c=J([]),B=()=>{if(typeof window>"u")return!0;const n=window.location.hostname;return n==="localhost"||n==="127.0.0.1"},j=()=>typeof window>"u"||B()?"https://你的正式域名":window.location.origin,$=s(()=>j()),i=s(()=>`${$.value}/clientauth`),y=s(()=>`${$.value}/adminapi.php/ClientAuthGateway`),T=s(()=>Number(C.userInfo?.aid||0)),l=s(()=>T.value>0?String(T.value):"请填写当前站点 aid"),g=s(()=>c.value[0]?.product_key||"请先到产品配置里创建 product_key"),z=s(()=>c.value.length===0?["- 当前还没有可用产品，请先在后台创建产品并拿到 product_key"]:c.value.map(n=>`- ${n.title}: ${n.product_key}`)),S=s(()=>`location /clientauth/ {
    if (!-e $request_filename) {
        rewrite ^/clientauth/(.*?)/?$ /adminapi.php/ClientAuthGateway/$1 last;
        break;
    }
}`),k=s(()=>`POST ${i.value}/login
Content-Type: application/json

{
  "aid": ${l.value},
  "product_key": "${g.value}",
  "username": "demo001",
  "password": "123456",
  "install_id": "9d2d1e9e-xxxx",
  "machine_hash": "sha256_xxx",
  "device_name": "MacBook Pro",
  "os_name": "macOS",
  "os_version": "15.0",
  "client_version": "1.0.0"
}`),w=s(()=>`const API_BASE = '${i.value}';
const aid = ${l.value};
const productKey = '${g.value}';

async function loginByPassword(payload: {
  username: string;
  password: string;
  install_id: string;
  machine_hash?: string;
  device_name?: string;
  os_name?: string;
  os_version?: string;
  client_version?: string;
}) {
  const res = await fetch(\`\${API_BASE}/login\`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      aid,
      product_key: productKey,
      ...payload,
    }),
  });
  return res.json();
}

async function activateByCode(payload: {
  auth_code: string;
  install_id: string;
  machine_hash?: string;
  device_name?: string;
  os_name?: string;
  os_version?: string;
  client_version?: string;
}) {
  const res = await fetch(\`\${API_BASE}/activate\`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      aid,
      product_key: productKey,
      ...payload,
    }),
  });
  return res.json();
}

async function refreshToken(refreshToken: string, clientVersion?: string) {
  const res = await fetch(\`\${API_BASE}/refresh\`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      aid,
      refresh_token: refreshToken,
      client_version: clientVersion || '1.0.0',
    }),
  });
  return res.json();
}

async function heartbeat(accessToken: string, clientVersion?: string) {
  const res = await fetch(\`\${API_BASE}/heartbeat\`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Authorization: \`Bearer \${accessToken}\`,
    },
    body: JSON.stringify({
      aid,
      client_version: clientVersion || '1.0.0',
    }),
  });
  return res.json();
}`),P=s(()=>`客户端授权中心对接说明

一、服务端基地址
1. 推荐短链基地址：${i.value}
2. 兜底基地址：${y.value}
3. 如果线上没有配置 /clientauth/* 的 Nginx 转发，请先直接使用 /adminapi.php/ClientAuthGateway/*。

二、接入前必须确认的参数
1. aid：租户 ID，客户端每次调用都要带。当前后台站点 aid：${l.value}
2. product_key：产品标识，由后台“产品配置”生成，客户端每次调用都要带。
3. install_id：客户端安装标识，首次启动生成并持久化，后续不要变。
4. machine_hash：设备机器摘要，推荐传。
5. client_version：客户端版本号，推荐每次都传。

当前系统产品标识：
${z.value.join(`
`)}

三、公开接口清单
1. POST /login
   用途：账号密码登录
2. POST /activate
   用途：授权码激活
3. POST /refresh
   用途：刷新 access_token
4. POST /logout
   用途：主动退出登录
5. GET 或 POST /me
   用途：获取当前登录用户信息
6. POST /heartbeat
   用途：心跳保活、更新客户端版本和最近在线时间

四、返回结构
1. 成功：
{
  "code": 200,
  "msg": "登录成功",
  "data": {
    "access_token": "...",
    "refresh_token": "...",
    "expire_time": 1760000000,
    "refresh_expire_time": 1761000000,
    "product": {...},
    "account": {...},
    "device": {...}
  }
}
2. 失败：
{
  "code": 400,
  "msg": "缺少 aid",
  "data": []
}
或
{
  "code": 401,
  "msg": "登录状态无效或已过期",
  "data": []
}

五、请求字段说明
1. login 必填：
   aid, product_key, username, password, install_id
2. activate 必填：
   aid, product_key, auth_code, install_id
3. refresh 必填：
   aid, refresh_token
4. logout / me / heartbeat 必填：
   aid + access_token
   access_token 推荐放在 Authorization: Bearer <token> 头里
5. 推荐附带：
   machine_hash, device_name, os_name, os_version, client_version

六、推荐接入流程
1. 客户端启动后，先读取本地 refresh_token。
2. 如果本地有 refresh_token，先调用 refresh。
3. refresh 成功：
   更新本地 access_token / refresh_token，直接进入主界面。
4. refresh 失败：
   显示登录页。
5. 用户如果走账号模式，调 login。
6. 用户如果走授权码模式，调 activate。
7. 登录成功后保存 access_token 和 refresh_token。
8. 心跳建议每 5 到 10 分钟调一次 heartbeat。
9. access_token 快过期时，调 refresh 续期。
10. 用户主动退出时调 logout。

七、登录示例
${k.value}

八、授权码激活示例
POST ${i.value}/activate
Content-Type: application/json

{
  "aid": ${l.value},
  "product_key": "${g.value}",
  "auth_code": "ABCD-1234-EFGH",
  "install_id": "9d2d1e9e-xxxx",
  "machine_hash": "sha256_xxx",
  "device_name": "MacBook Pro",
  "os_name": "macOS",
  "os_version": "15.0",
  "client_version": "1.0.0"
}

九、续期示例
POST ${i.value}/refresh
Content-Type: application/json

{
  "aid": ${l.value},
  "refresh_token": "cr_xxx",
  "client_version": "1.0.0"
}

十、me / heartbeat / logout 示例
1. me：
GET ${i.value}/me?aid=${l.value}
Authorization: Bearer ca_xxx

2. heartbeat：
POST ${i.value}/heartbeat
Authorization: Bearer ca_xxx
Content-Type: application/json

{
  "aid": ${l.value},
  "client_version": "1.0.0"
}

3. logout：
POST ${i.value}/logout
Authorization: Bearer ca_xxx
Content-Type: application/json

{
  "aid": ${l.value}
}

十一、客户端本地存储建议
1. install_id：首次生成后长期保存。
2. access_token：短期存储，可内存 + 本地缓存。
3. refresh_token：长期存储，建议安全保存。
4. 当前用户信息：可缓存 account / device / expire_time。

十二、上线注意项
1. 一定要确认 aid 和 product_key 正确，否则服务端会直接拒绝。
2. install_id 不要每次启动都变，否则服务端会把它当成新设备。
3. 如果正式环境想走短链 /clientauth/*，Nginx 需要加下面这段转发：
${S.value}
4. 如果暂时没改 Nginx，就先直接使用 ${y.value} 这组地址。
5. 账号被冻结、授权码停用、设备被冻结、会话失效时，服务端会返回非 200 code，客户端要弹出明确提示。`),b=async()=>{try{const n=await E.getProductList();n.status===1&&Array.isArray(n.data)&&(c.value=n.data)}catch(n){console.error(n)}},x=async n=>{try{await navigator.clipboard.writeText(n),O.success("已复制到剪贴板")}catch(e){console.error(e),O.error("复制失败，请手动复制")}};return K(()=>{b()}),(n,e)=>{const h=d("a-button"),A=d("a-space"),N=d("a-alert"),r=d("a-card"),m=d("a-textarea");return p(),_("div",F,[t(r,{bordered:!1},{title:a(()=>[...e[3]||(e[3]=[v("客户端对接说明",-1)])]),extra:a(()=>[t(A,null,{default:a(()=>[t(h,{onClick:b},{default:a(()=>[...e[4]||(e[4]=[v("刷新产品标识",-1)])]),_:1}),t(h,{onClick:e[0]||(e[0]=u=>x(k.value))},{default:a(()=>[...e[5]||(e[5]=[v("复制登录示例",-1)])]),_:1}),t(h,{onClick:e[1]||(e[1]=u=>x(w.value))},{default:a(()=>[...e[6]||(e[6]=[v("复制 TS 示例",-1)])]),_:1}),t(h,{type:"primary",onClick:e[2]||(e[2]=u=>x(P.value))},{default:a(()=>[...e[7]||(e[7]=[v("复制完整说明",-1)])]),_:1})]),_:1})]),default:a(()=>[t(A,{direction:"vertical",size:"large",style:{width:"100%"}},{default:a(()=>[t(N,{type:"info","show-icon":"",message:"这页就是发给客户端技术同事的对接说明。建议直接复制“完整说明”，里面已经包含接口、字段、续期流程和上线注意项。"}),o("div",M,[t(r,{size:"small",title:"推荐公开基地址"},{default:a(()=>[o("div",H,f(i.value),1),e[8]||(e[8]=o("div",{class:"sub"},"推荐客户端优先使用这组短链接口。",-1))]),_:1}),t(r,{size:"small",title:"兜底基地址"},{default:a(()=>[o("div",q,f(y.value),1),e[9]||(e[9]=o("div",{class:"sub"},"如果线上 Nginx 还没加短链转发，先直接用这组地址也能接。",-1))]),_:1}),t(r,{size:"small",title:"当前产品标识"},{default:a(()=>[c.value.length>0?(p(),_("div",U,[(p(!0),_(L,null,D(c.value,u=>(p(),_("div",{key:u.id,class:"product-item"},[o("span",null,f(u.title),1),o("code",null,f(u.product_key),1)]))),128))])):(p(),_("div",X,"还没有配置产品，请先到“产品配置”里新增一条。"))]),_:1})]),t(r,{size:"small",title:"推荐接入策略"},{default:a(()=>[...e[10]||(e[10]=[o("ol",{class:"ordered-list"},[o("li",null,"客户端首次启动时生成并持久化一个 `install_id`，后续不要变。"),o("li",null,"如果本地有 `refresh_token`，先走 `refresh` 静默续期；失败再显示登录页。"),o("li",null,"账号模式用 `login`，授权码模式用 `activate`。"),o("li",null,"登录成功后保存 `access_token` 和 `refresh_token`，`me / heartbeat / logout` 使用 `access_token`。"),o("li",null,"建议定时心跳 5 到 10 分钟一次，接近过期时走 `refresh`。")],-1)])]),_:1}),t(r,{size:"small",title:"Nginx 短链转发"},{default:a(()=>[t(m,{value:S.value,rows:8,readonly:""},null,8,["value"])]),_:1}),t(r,{size:"small",title:"完整对接说明（可直接复制）"},{default:a(()=>[t(m,{value:P.value,rows:34,readonly:""},null,8,["value"])]),_:1}),t(r,{size:"small",title:"登录请求示例"},{default:a(()=>[t(m,{value:k.value,rows:18,readonly:""},null,8,["value"])]),_:1}),t(r,{size:"small",title:"TypeScript 接入示例"},{default:a(()=>[t(m,{value:w.value,rows:28,readonly:""},null,8,["value"])]),_:1})]),_:1})]),_:1})])}}}),ne=I(Y,[["__scopeId","data-v-4b0dff30"]]);export{ne as default};

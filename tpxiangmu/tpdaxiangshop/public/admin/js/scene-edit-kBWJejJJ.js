import{d as Lt,f as C,e as b,w as He,o as Ot,j as Ia,V as N,a1 as k,X as c,a3 as Pe,a0 as A,W as S,$ as s,k as a,a4 as x,Y as o,G as f,a7 as Be,a5 as Ba,ab as Va,F as K,a2 as _e,u as Et,aj as It,B as Ne,n as X}from"./vue-vendor-BH3QE-KO.js";import{I as Ka}from"./index-tbAVIOWA.js";import{F as La}from"./FormPageShell-CBZY5SYv.js";import{F as Bt}from"./FormSectionCard-x94uB5-y.js";import{_ as Rt}from"./index-CTO9TwcJ.js";import{k as Oa,i as Ra,l as Ja}from"./panorama-CEN3LaJt.js";import{bv as Vt,h as $}from"./antd-vendor-BXeG7MAg.js";import"./index-BPqrkvl_.js";import"./index.vue_vue_type_style_index_0_scoped_c3af377c_lang-BfWrGaKE.js";import"./imageUrl-DAg2mWyl.js";import"./UploadButton.vue_vue_type_script_setup_true_lang-BgW14SLD.js";/* empty css                                                                  */import"./DragUpload-CjoFvy2b.js";/* empty css                                                                   */import"./utils-vendor-dR2luS_Z.js";import"./charts-vendor-CwCbZZ1s.js";const Wa={key:0,class:"visual-canvas__header"},Ya={class:"visual-canvas__desc"},Xa=["srcdoc"],Ga={key:2,class:"visual-canvas__footer"},Qa="20260508_editor_frame_v11",Za=Lt({__name:"PanoramaVisualCanvas",props:{project:{},scene:{},activeHotspot:{},interactionMode:{default:"assign"},allowQuickCreate:{type:Boolean,default:!0},chrome:{type:Boolean,default:!0},height:{default:520},selectedPosition:{default:null},activeLabel:{default:""},editorKind:{default:"hotspot"},modeLabel:{},modeDescription:{},footerText:{},editorHint:{}},emits:["pick-position","select-hotspot","navigate-hotspot","request-create-hotspot","drag-hotspot","view-change"],setup(mt,{expose:ze,emit:Ve}){const l=mt,R=Ve,ne=C(null),J=C(!1),se=C(0),G=C(!1),L=C("{}"),le=C("{}"),E=C("{}"),Q=C(""),M=C(""),F=C(""),D=b(()=>l.chrome),m=b(()=>({"--visual-canvas-height":typeof l.height=="number"?`${l.height}px`:String(l.height||"520px")}));function W(){const p=String(l.project.adapter_type||"equirectangular"),_=String(l.scene.panorama_url||"").trim(),v=l.scene.adapter_config&&typeof l.scene.adapter_config=="object"?l.scene.adapter_config:{};if(p==="equirectangular_tiles"){const z=typeof v.panorama=="object"&&v.panorama?v.panorama:{};return String(z.tileUrl||_).trim()}if(p==="cubemap"){const z=typeof v.panorama=="object"&&v.panorama?v.panorama:{},T=String(z.type||"net");if(T==="stripe")return String(v.stripe||_).trim();if(T==="separate"){const U=v.separate||{};return String(U.front||U.left||U.right||U.back||U.top||U.bottom||_).trim()}return String(v.net||_).trim()}return typeof v.panorama=="string"&&String(v.panorama).trim()?String(v.panorama).trim():_}const I=b(()=>!!W()),P=b(()=>l.selectedPosition!==null&&l.selectedPosition!==void 0&&l.selectedPosition!==""?ee(l.selectedPosition):ee(l.activeHotspot?.position||"")),Ye=b(()=>l.activeLabel?l.activeLabel:l.activeHotspot?l.activeHotspot.name?.trim()||"当前热点":"未选择热点"),Xe=b(()=>{const p=P.value;return p?`${p.yaw.toFixed(4)},${p.pitch.toFixed(4)}`:"未取点"}),ge=b(()=>l.modeLabel?l.modeLabel:l.interactionMode==="create"?"新增落点模式":"当前热点取点"),$e=b(()=>l.interactionMode==="create"?"gold":"blue"),Z=b(()=>l.modeDescription?l.modeDescription:l.interactionMode==="create"?"当前模式会在你点击全景画布时直接新增热点，并把落点写进去。":"当前模式会把你在画布点击的位置回写到已选中的热点，操作方式与参考代码一致。"),be=b(()=>l.footerText?l.footerText:l.interactionMode==="create"?"当前规则：点击空白区域会新增一个热点并写入该位置；点击已有图钉仍可切换到对应热点。":"当前规则：点击已有图钉可切换热点，点击空白区域可回写当前热点的 yaw,pitch。"),Ge=b(()=>l.editorHint?l.editorHint:l.interactionMode==="create"?"新增模式：拖动画面后单击落点，会直接创建热点":"编辑模式：拖动画面后单击落点，会更新当前热点坐标"),B=b(()=>({editor_kind:l.editorKind,interaction_mode:l.interactionMode,mode_label:ge.value,mode_description:Z.value,footer_text:be.value,allow_create_hotspot:l.allowQuickCreate}));function je(){const p=String(l.project.adapter_type||"equirectangular"),_=W(),v=l.scene.adapter_config&&typeof l.scene.adapter_config=="object"?l.scene.adapter_config:{};if(p==="cubemap"){const z=typeof v.panorama=="object"&&v.panorama?v.panorama:{},T=["net","stripe","separate"].includes(String(z.type||""))?String(z.type):"net";return{...v,panorama:{...z,type:T},net:String(v.net||(T==="net"?_:"")).trim(),stripe:String(v.stripe||(T==="stripe"?_:"")).trim(),separate:{left:"",front:"",right:"",back:"",top:"",bottom:"",...v.separate||{}},flipTopBottom:Number(v.flipTopBottom??0)}}if(p==="equirectangular_tiles"){const z=typeof v.panorama=="object"&&v.panorama?v.panorama:{};return{...v,resolution:Number(v.resolution||l.scene.resolution||64),panorama:{baseUrl:String(z.baseUrl||"").trim(),tileUrl:String(z.tileUrl||_).trim(),width:z.width??"",cols:z.cols??"",rows:z.rows??""},baseBlur:Number(v.baseBlur??0),showErrorTile:Number(v.showErrorTile??1),status:Number(v.status??1),panoData:{...v.panoData||{}}}}return{...v,resolution:Number(v.resolution||l.scene.resolution||64),panorama:typeof v.panorama=="string"&&String(v.panorama).trim()?String(v.panorama).trim():_,status:Number(v.status??0),panoData:{...v.panoData||{}}}}function ee(p){if(!p&&p!==0)return null;if(typeof p=="object"){const ye=Number(p.yaw),Oe=Number(p.pitch);return!Number.isFinite(ye)||!Number.isFinite(Oe)?null:{yaw:ye,pitch:Oe}}const _=String(p||"").trim();if(!_)return null;const[v="",z=""]=_.split(","),T=Number(v),U=Number(z);return!Number.isFinite(T)||!Number.isFinite(U)?null:{yaw:T,pitch:U}}function te(p){return JSON.stringify(p).replace(/</g,"\\u003c").replace(/-->/g,"--\\>").replace(/<\/script/gi,"<\\/script")}function r(p){return String(p||"").replace(/[&<>"']/g,_=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"})[_]||_)}function Qe(p){const _=String(p||"").trim();return _?_==="left"?"up_left":_==="right"?"up_right":_:"up"}function H(p){const _=Number(p);return!Number.isFinite(_)||_<=0?44:Math.min(96,Math.max(36,Math.round(_)))}const O=b(()=>({id:Number(l.scene.id||0),scene_key:String(l.scene.scene_key||""),title:String(l.scene.title||""),caption:String(l.scene.caption||l.scene.title||""),description:String(l.scene.description||""),panorama_url:W(),adapter_config:je(),default_zoom_lvl:Number(l.scene.default_zoom_lvl??0),default_yaw:Number(l.scene.default_yaw??0),default_pitch:Number(l.scene.default_pitch??0),hotspots:(l.scene.hotspots||[]).map((p,_)=>({id:Number(p.id||0),hotspot_key:String(p.hotspot_key||`hotspot_${_+1}`),name:String(p.name||`热点 ${_+1}`),position:String(p.position||""),action_type:String(p.action_type||"scene"),target_scene_key:String(p.target_scene_key||""),action_value:String(p.action_value||""),business_type:String(p.business_type||""),business_id:Number(p.business_id||0),status:Number(p.status??1),description:String(p.description||""),sort:Number(p.sort||_+1),ext:{...p.ext||{},direction_icon:Qe(p.ext&&typeof p.ext=="object"&&"direction_icon"in p.ext?p.ext.direction_icon:"up"),direction_size:H(p.ext&&typeof p.ext=="object"&&"direction_size"in p.ext?Number(p.ext.direction_size||0):0),custom_icon:p.ext&&typeof p.ext=="object"&&"custom_icon"in p.ext?String(p.ext.custom_icon||"").trim():""}}))})),Ze=b(()=>({adapter_type:String(l.project.adapter_type||"equirectangular"),description:String(l.project.description||""),min_fov:Number(l.project.min_fov||30),max_fov:Number(l.project.max_fov||90),start_pitch:Number(l.project.start_pitch??0),start_yaw:Number(l.project.start_yaw??0),start_zoom_lvl:Number(l.project.start_zoom_lvl??0),panoscenes:[O.value]})),Ue=b(()=>[l.project.adapter_type,l.scene.id,l.scene.scene_key,W(),l.scene.resolution,JSON.stringify(l.scene.adapter_config||{}),l.project.start_pitch??0,l.project.start_yaw??0,l.project.start_zoom_lvl??0,l.scene.default_zoom_lvl??0,l.scene.default_yaw??0,l.scene.default_pitch??0,JSON.stringify(P.value||null),l.activeLabel||"",l.editorKind||"hotspot",l.modeLabel||"",l.modeDescription||"",l.footerText||"",l.editorHint||""].join("::")),et=b(()=>JSON.stringify({scene:O.value,activeHotspotKey:String(l.activeHotspot?.hotspot_key||""),position:P.value,editorMeta:B.value}));function Te(){L.value=te(O.value),le.value=te(Ze.value),E.value=te(P.value),Q.value=String(l.activeHotspot?.hotspot_key||"")}function ue(p,_){const v=ne.value?.contentWindow;v&&v.postMessage({type:p,payload:_||{}},"*")}function Ke(p={}){!J.value||!I.value||ue("panorama-editor-sync",{scene:O.value,activeHotspotKey:String(l.activeHotspot?.hotspot_key||""),position:P.value,focus:!!p.focus,editorMeta:B.value})}function pe(){J.value=!1,M.value="",F.value="",Te(),se.value+=1}function Le(){const p=P.value;if(p){if(!J.value){G.value=!0,pe();return}ue("panorama-editor-focus-active",{position:p})}}function De(p){if(p.source!==ne.value?.contentWindow)return;const _=p.data;if(_?.type){if(_.type==="panorama-editor-ready"){J.value=!0,M.value="",F.value="";const v=G.value;G.value=!1,Ke({focus:v});return}if(_.type==="panorama-editor-position"){const v=String(_.payload?.position||"").trim();if(!v)return;R("pick-position",v);const z=Number(_.payload?.yaw),T=Number(_.payload?.pitch),U=_.payload?.zoom;R("view-change",{yaw:Number.isFinite(z)?z:0,pitch:Number.isFinite(T)?T:0,zoom:typeof U=="number"&&Number.isFinite(U)?U:null});return}if(_.type==="panorama-editor-drag-hotspot"){const v=String(_.payload?.hotspot_key||"").trim(),z=String(_.payload?.position||"").trim(),T=Number(_.payload?.yaw),U=Number(_.payload?.pitch);if(!v||!z||!Number.isFinite(T)||!Number.isFinite(U))return;R("drag-hotspot",{hotspotKey:v,position:z,yaw:T,pitch:U});return}if(_.type==="panorama-editor-select-hotspot"){const v=String(_.payload?.hotspot_key||"").trim();if(!v)return;R("select-hotspot",v);return}if(_.type==="panorama-editor-navigate-hotspot"){const v=String(_.payload?.direction||"").trim();if(v!=="prev"&&v!=="next")return;R("navigate-hotspot",v);return}if(_.type==="panorama-editor-request-create-hotspot"){R("request-create-hotspot");return}_.type==="panorama-editor-error"&&(J.value=!1,M.value=String(_.payload?.message||"全景画布加载失败"),F.value=String(_.payload?.detail||""))}}const tt=b(()=>{if(!I.value)return"";const p=`${Qa}_${se.value}`;return`<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="/panorama_h5/css/main.css?v=${p}" />
  <style>
    html, body { width: 100%; height: 100%; margin: 0; padding: 0; background: #04131b; }
    #viewer { width: 100vw; height: 100vh; cursor: crosshair; }
    #viewer .psv-container { cursor: crosshair; }
    .editor-mask {
      position: fixed;
      top: 18px;
      left: 18px;
      z-index: 99;
      padding: 10px 14px;
      border-radius: 999px;
      background: rgba(6, 19, 31, 0.72);
      color: #fff;
      font-size: 12px;
      line-height: 1.4;
      backdrop-filter: blur(8px);
    }
    .editor-boot-error {
      position: fixed;
      inset: 0;
      z-index: 999;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
      background:
        radial-gradient(circle at top, rgba(21, 76, 115, 0.28) 0%, rgba(3, 13, 20, 0.88) 54%),
        rgba(3, 11, 17, 0.88);
      color: #fff;
    }
    .editor-boot-error__card {
      width: min(520px, 100%);
      padding: 24px 26px;
      border-radius: 22px;
      background: rgba(7, 22, 34, 0.9);
      border: 1px solid rgba(255, 111, 111, 0.34);
      box-shadow: 0 24px 64px rgba(0, 0, 0, 0.34);
    }
    .editor-boot-error__title {
      font-size: 20px;
      font-weight: 800;
    }
    .editor-boot-error__desc {
      margin-top: 10px;
      font-size: 14px;
      line-height: 1.7;
      color: rgba(255, 255, 255, 0.86);
    }
    .editor-boot-error__detail {
      margin-top: 12px;
      padding: 10px 12px;
      border-radius: 12px;
      background: rgba(255, 255, 255, 0.08);
      font-size: 12px;
      line-height: 1.6;
      color: rgba(255, 255, 255, 0.72);
      word-break: break-all;
    }
    .editor-hud {
      position: fixed;
      right: 18px;
      top: 92px;
      z-index: 99;
      width: min(320px, calc(100vw - 36px));
      display: flex;
      flex-direction: column;
      gap: 10px;
      pointer-events: none;
    }
    .editor-hud__panel,
    .editor-hud__legend {
      padding: 14px 16px;
      border-radius: 18px;
      background: rgba(6, 19, 31, 0.76);
      color: #fff;
      box-shadow: 0 18px 36px rgba(1, 8, 14, 0.32);
      backdrop-filter: blur(10px);
    }
    .editor-hud__label {
      font-size: 11px;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: rgba(255, 255, 255, 0.62);
    }
    .editor-hud__title {
      margin-top: 6px;
      font-size: 18px;
      font-weight: 700;
      line-height: 1.3;
    }
    .editor-hud__subtitle {
      margin-top: 4px;
      font-size: 12px;
      line-height: 1.6;
      color: rgba(255, 255, 255, 0.78);
    }
    .editor-hud__meta {
      margin-top: 12px;
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }
    .editor-chip {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 10px;
      border-radius: 999px;
      font-size: 11px;
      line-height: 1;
      background: rgba(255, 255, 255, 0.08);
      color: rgba(255, 255, 255, 0.9);
    }
    .editor-chip__dot {
      width: 8px;
      height: 8px;
      border-radius: 999px;
      flex: 0 0 auto;
    }
    .editor-hud__stats {
      margin-top: 12px;
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 10px;
    }
    .editor-stat {
      padding: 10px 12px;
      border-radius: 14px;
      background: rgba(255, 255, 255, 0.06);
    }
    .editor-stat__label {
      font-size: 11px;
      color: rgba(255, 255, 255, 0.64);
    }
    .editor-stat__value {
      margin-top: 6px;
      font-size: 14px;
      font-weight: 700;
      color: #fff;
    }
    .editor-hud__legend-title {
      font-size: 11px;
      color: rgba(255, 255, 255, 0.64);
    }
    .editor-hud__legend-items {
      margin-top: 10px;
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 8px;
    }
    .editor-quickbar {
      position: fixed;
      left: 18px;
      bottom: 18px;
      z-index: 120;
      display: flex;
      align-items: center;
      gap: 10px;
      width: min(640px, calc(100vw - 36px));
      padding: 12px;
      border-radius: 22px;
      background: rgba(6, 19, 31, 0.78);
      color: #fff;
      box-shadow: 0 18px 36px rgba(1, 8, 14, 0.32);
      backdrop-filter: blur(10px);
    }
    .editor-quickbar__summary {
      min-width: 0;
      flex: 1 1 auto;
      padding: 0 4px;
    }
    .editor-quickbar__caption {
      font-size: 11px;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      color: rgba(255, 255, 255, 0.52);
    }
    .editor-quickbar__title {
      margin-top: 4px;
      font-size: 15px;
      font-weight: 700;
      line-height: 1.35;
      color: #fff;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .editor-quickbar__meta {
      margin-top: 4px;
      font-size: 12px;
      color: rgba(255, 255, 255, 0.72);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .editor-quickbar__actions {
      display: flex;
      align-items: center;
      gap: 8px;
      flex: 0 0 auto;
    }
    .editor-quickbar__button {
      min-width: 78px;
      height: 40px;
      padding: 0 14px;
      border: 1px solid rgba(255, 255, 255, 0.12);
      border-radius: 12px;
      background: rgba(255, 255, 255, 0.08);
      color: #fff;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      transition:
        background 0.2s ease,
        border-color 0.2s ease,
        transform 0.2s ease,
        opacity 0.2s ease;
    }
    .editor-quickbar__button:hover {
      border-color: rgba(115, 211, 238, 0.38);
      background: rgba(34, 211, 238, 0.14);
      transform: translateY(-1px);
    }
    .editor-quickbar__button:disabled {
      opacity: 0.42;
      cursor: not-allowed;
      transform: none;
      border-color: rgba(255, 255, 255, 0.08);
      background: rgba(255, 255, 255, 0.05);
    }
    .editor-quickbar__button--accent {
      border-color: rgba(251, 191, 36, 0.34);
      background: linear-gradient(135deg, rgba(251, 191, 36, 0.28) 0%, rgba(217, 119, 6, 0.2) 100%);
      color: #fff7db;
    }
    .editor-quickbar__button--accent:hover {
      border-color: rgba(251, 191, 36, 0.54);
      background: linear-gradient(135deg, rgba(251, 191, 36, 0.36) 0%, rgba(217, 119, 6, 0.28) 100%);
    }
    .editor-view-controls {
      position: fixed;
      top: 18px;
      right: 18px;
      z-index: 140;
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 10px 12px;
      border-radius: 18px;
      background: rgba(6, 19, 31, 0.8);
      color: #fff;
      box-shadow: 0 18px 36px rgba(1, 8, 14, 0.32);
      backdrop-filter: blur(10px);
      pointer-events: auto;
    }
    .editor-view-controls__group {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .editor-view-controls__button {
      width: 42px;
      height: 42px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border: 1px solid rgba(255, 255, 255, 0.14);
      border-radius: 12px;
      background: rgba(255, 255, 255, 0.08);
      color: #fff;
      font-size: 20px;
      font-weight: 700;
      line-height: 1;
      cursor: pointer;
      transition:
        background 0.2s ease,
        border-color 0.2s ease,
        transform 0.2s ease,
        opacity 0.2s ease;
    }
    .editor-view-controls__button:hover {
      border-color: rgba(115, 211, 238, 0.38);
      background: rgba(34, 211, 238, 0.14);
      transform: translateY(-1px);
    }
    .editor-view-controls__button:disabled {
      opacity: 0.4;
      cursor: not-allowed;
      transform: none;
      border-color: rgba(255, 255, 255, 0.08);
      background: rgba(255, 255, 255, 0.05);
    }
    .editor-view-controls__button--reset {
      width: auto;
      min-width: 52px;
      padding: 0 12px;
      font-size: 13px;
      font-weight: 600;
    }
    .editor-view-controls__meta {
      min-width: 72px;
      font-size: 12px;
      font-weight: 600;
      text-align: right;
      color: rgba(255, 255, 255, 0.82);
    }
    .editor-viewframe {
      position: fixed;
      inset: 0;
      z-index: 130;
      pointer-events: none;
    }
    .editor-viewframe__shade {
      position: absolute;
      background: rgba(3, 10, 18, 0.28);
      backdrop-filter: blur(2px);
    }
    .editor-viewframe__shade--top {
      top: 0;
      left: 0;
      right: 0;
      bottom: calc(50% + 152px);
    }
    .editor-viewframe__shade--right {
      top: calc(50% - 152px);
      right: 0;
      bottom: calc(50% - 152px);
      left: calc(50% + 240px);
    }
    .editor-viewframe__shade--bottom {
      top: calc(50% + 152px);
      left: 0;
      right: 0;
      bottom: 0;
    }
    .editor-viewframe__shade--left {
      top: calc(50% - 152px);
      right: calc(50% + 240px);
      bottom: calc(50% - 152px);
      left: 0;
    }
    .editor-viewframe__box {
      position: absolute;
      top: 50%;
      left: 50%;
      width: min(480px, calc(100vw - 120px));
      height: min(304px, calc(100vh - 190px));
      min-width: 300px;
      min-height: 188px;
      transform: translate(-50%, -50%);
      border: 1px dashed rgba(255, 255, 255, 0.82);
      box-shadow:
        0 0 0 1px rgba(9, 28, 44, 0.18),
        0 24px 70px rgba(0, 0, 0, 0.22);
      pointer-events: none;
    }
    .editor-viewframe__corner {
      position: absolute;
      width: 42px;
      height: 42px;
      border-color: #fff;
      filter: drop-shadow(0 2px 6px rgba(0, 0, 0, 0.35));
    }
    .editor-viewframe__corner--tl {
      top: -2px;
      left: -2px;
      border-top: 4px solid;
      border-left: 4px solid;
    }
    .editor-viewframe__corner--tr {
      top: -2px;
      right: -2px;
      border-top: 4px solid;
      border-right: 4px solid;
    }
    .editor-viewframe__corner--br {
      right: -2px;
      bottom: -2px;
      border-right: 4px solid;
      border-bottom: 4px solid;
    }
    .editor-viewframe__corner--bl {
      bottom: -2px;
      left: -2px;
      border-bottom: 4px solid;
      border-left: 4px solid;
    }
    .editor-viewframe__cross {
      position: absolute;
      top: 50%;
      left: 50%;
      background: rgba(255, 255, 255, 0.92);
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.34);
      transform: translate(-50%, -50%);
    }
    .editor-viewframe__cross--x {
      width: 58px;
      height: 2px;
    }
    .editor-viewframe__cross--y {
      width: 2px;
      height: 58px;
    }
    .editor-viewframe__button {
      position: absolute;
      left: 50%;
      bottom: 22px;
      height: 42px;
      padding: 0 24px;
      border: 0;
      border-radius: 4px;
      background: #1787e8;
      color: #fff;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      box-shadow: 0 12px 28px rgba(23, 135, 232, 0.36);
      transform: translateX(-50%);
      pointer-events: auto;
    }
    .editor-viewframe__button:hover {
      background: #0f79d4;
    }
    .marker-title {
      font-size: 13px;
      font-weight: 700;
      line-height: 1.4;
      color: #10294f;
    }
    .marker-row {
      margin-top: 6px;
      display: flex;
      align-items: center;
      gap: 6px;
      flex-wrap: wrap;
    }
    .marker-intro {
      margin-top: 6px;
      font-size: 12px;
      line-height: 1.55;
      color: #526272;
    }
    .marker-position {
      margin-top: 6px;
      font-size: 11px;
      color: #6f8295;
    }
    .marker-badge {
      display: inline-flex;
      align-items: center;
      padding: 4px 8px;
      border-radius: 999px;
      font-size: 11px;
      line-height: 1;
      color: #fff;
    }
    .marker-badge--scene { background: #1c7ed6; }
    .marker-badge--info { background: #d97706; }
    .marker-badge--link { background: #db2777; }
    .marker-badge--business { background: #059669; }
    .marker-badge--disabled { background: #6b7280; }
    .editor-direction-marker {
      overflow: visible !important;
      background: transparent !important;
    }
    .editor-direction-marker .psv-marker {
      overflow: visible;
    }
    @media (max-width: 900px) {
      .editor-hud {
        top: auto;
        right: 18px;
        left: 18px;
        bottom: 128px;
        width: auto;
      }
      .editor-hud__stats,
      .editor-hud__legend-items {
        grid-template-columns: 1fr;
      }
      .editor-quickbar {
        flex-direction: column;
        align-items: stretch;
      }
      .editor-quickbar__actions {
        width: 100%;
        flex-wrap: wrap;
      }
      .editor-quickbar__button {
        flex: 1 1 calc(50% - 4px);
      }
      .editor-viewframe__shade--top {
        bottom: calc(50% + 118px);
      }
      .editor-viewframe__shade--right {
        top: calc(50% - 118px);
        bottom: calc(50% - 118px);
        left: calc(50% + min(190px, calc((100vw - 72px) / 2)));
      }
      .editor-viewframe__shade--bottom {
        top: calc(50% + 118px);
      }
      .editor-viewframe__shade--left {
        top: calc(50% - 118px);
        right: calc(50% + min(190px, calc((100vw - 72px) / 2)));
        bottom: calc(50% - 118px);
      }
      .editor-viewframe__box {
        width: min(380px, calc(100vw - 72px));
        height: 236px;
        min-width: 260px;
        min-height: 178px;
      }
      .editor-viewframe__button {
        bottom: 16px;
        width: calc(100% - 48px);
        padding: 0 12px;
        font-size: 13px;
      }
    }
  </style>
</head>
<body>
  <div id="viewer"></div>
  <div class="editor-mask">${r(Ge.value)}</div>
  <script>
    window.__showPanoramaEditorError = function(message, detail) {
      var root = document.getElementById('editorBootError');
      if (!root) {
        root = document.createElement('div');
        root.id = 'editorBootError';
        root.className = 'editor-boot-error';
        document.body.appendChild(root);
      }
      function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function(char) {
          return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char];
        });
      }
      root.innerHTML = '<div class="editor-boot-error__card"><div class="editor-boot-error__title">全景画布加载失败</div><div class="editor-boot-error__desc">' + escapeHtml(message || '编辑器初始化失败') + '</div>' + (detail ? '<div class="editor-boot-error__detail">' + escapeHtml(detail) + '</div>' : '') + '</div>';
      if (window.parent && window.parent !== window) {
        window.parent.postMessage({ type: 'panorama-editor-error', payload: { message: String(message || ''), detail: String(detail || '') } }, '*');
      }
    };
    window.addEventListener('error', function(event) {
      if (event && event.filename && String(event.filename).indexOf('/panorama_h5/js/') > -1) {
        window.__showPanoramaEditorError(event.message || '全景编辑脚本执行失败', event.filename || '');
      }
    });
    window.Config = {
      moduleurl: '',
      __PUBLIC__: '',
      upload: {
        cdnurl: ''
      }
    };
    window._panorama = ${le.value};
    window._scene = ${L.value};
    window._position = ${E.value};
    window._activeHotspotKey = ${te(Q.value)};
    window._editorMeta = ${te(B.value)};
  <\/script>
  <script src="/panorama_h5/js/manifest.js?v=${p}" onerror="window.__showPanoramaEditorError && window.__showPanoramaEditorError('全景运行时加载失败', this.src)"><\/script>
  <script src="/panorama_h5/js/vendor.js?v=${p}" onerror="window.__showPanoramaEditorError && window.__showPanoramaEditorError('全景依赖库加载失败', this.src)"><\/script>
  <script src="/panorama_h5/js/editor-frame.js?v=${p}" onerror="window.__showPanoramaEditorError && window.__showPanoramaEditorError('全景编辑器脚本加载失败', this.src)"><\/script>
</body>
</html>`}),at=b(()=>`${se.value}::${Ue.value}`);return He(Ue,()=>{J.value=!1,M.value="",F.value="",Te(),se.value+=1},{immediate:!0}),He(et,()=>{Te(),Ke()},{immediate:!1}),He(()=>l.interactionMode,()=>{pe()}),He(I,p=>{p||(J.value=!1,G.value=!1,M.value="",F.value="")}),Ot(()=>{window.addEventListener("message",De)}),Ia(()=>{window.removeEventListener("message",De)}),ze({refreshFrame:pe,focusActiveHotspot:Le}),(p,_)=>{const v=N("a-tag"),z=N("a-button"),T=N("a-space"),U=N("a-alert"),ye=N("a-empty");return c(),k("div",{class:Pe(["visual-canvas",{"visual-canvas--bare":!D.value}])},[D.value?(c(),k("div",Wa,[s("div",null,[_[0]||(_[0]=s("div",{class:"visual-canvas__title"},"全景画布",-1)),s("div",Ya,x(Z.value),1)]),a(T,{wrap:""},{default:o(()=>[a(v,{color:$e.value},{default:o(()=>[f(x(ge.value),1)]),_:1},8,["color"]),a(v,{color:"blue"},{default:o(()=>[f(x(Ye.value),1)]),_:1}),a(v,null,{default:o(()=>[f(x(Xe.value),1)]),_:1}),a(z,{size:"small",onClick:pe},{default:o(()=>[..._[1]||(_[1]=[f("刷新画布",-1)])]),_:1})]),_:1})])):A("",!0),M.value?(c(),S(U,{key:1,type:"error","show-icon":"",message:M.value,description:F.value||"请检查全景资源、适配器配置和 /panorama_h5 静态资源是否可访问。"},null,8,["message","description"])):A("",!0),s("div",{class:Pe(["visual-canvas__stage",{"is-empty":!I.value}]),style:Be(m.value)},[I.value?(c(),k("iframe",{ref_key:"frameRef",ref:ne,key:at.value,class:"visual-canvas__frame",srcdoc:tt.value},null,8,Xa)):(c(),S(ye,{key:1,description:"请先为当前场景选择全景图，画布才可加载"}))],6),D.value?(c(),k("div",Ga,x(be.value),1)):A("",!0)],2)}}}),Kt=Rt(Za,[["__scopeId","data-v-63b7dd60"]]),eo={class:"scene-edit-page"},to={class:"page-savebar"},ao={class:"page-savebar__meta"},oo={class:"page-savebar__value"},ro={class:"summary-strip"},io={class:"summary-item"},no={class:"summary-item__value summary-item__value--small"},so={class:"summary-item"},lo={class:"summary-item__value summary-item__value--small"},uo={class:"summary-item"},po={class:"summary-item__value"},co={class:"summary-item"},vo={class:"summary-item__value summary-item__value--small"},mo={class:"asset-picker"},fo={class:"asset-preview"},_o={key:1,class:"asset-empty"},go={class:"asset-actions"},bo={key:0,class:"asset-picker"},yo={class:"asset-preview"},xo={key:1,class:"asset-empty"},ko={class:"asset-actions"},wo={class:"form-help"},ho={class:"asset-picker"},So={class:"asset-preview"},Co={key:1,class:"asset-empty"},No={class:"asset-actions"},Ho={class:"asset-picker"},Po={class:"asset-preview"},zo={key:1,class:"asset-empty"},$o={class:"asset-actions"},jo={class:"asset-picker"},Uo={class:"asset-preview"},To={key:1,class:"asset-empty"},Do={class:"asset-actions"},Mo={class:"asset-picker"},Fo={class:"asset-preview"},qo={key:1,class:"asset-empty"},Ao={class:"asset-actions"},Eo={class:"workbench-overview"},Io={class:"workbench-overview__card"},Bo={class:"workbench-overview__value"},Vo={class:"workbench-overview__meta"},Ko={class:"workbench-overview__card"},Lo={class:"workbench-overview__value"},Oo={class:"workbench-overview__card"},Ro={class:"workbench-overview__value"},Jo={class:"workbench-overview__meta"},Wo={class:"workbench-overview__card"},Yo={class:"workbench-overview__value workbench-overview__value--small"},Xo={class:"operation-shell"},Go={class:"operation-shell__heading"},Qo={class:"operation-shell__title"},Zo={class:"operation-shell__desc"},er={class:"operation-rail"},tr=["onClick"],ar={class:"operation-rail__icon"},or={class:"operation-rail__label"},rr={class:"operation-stage"},ir={class:"operation-stage__toolbar"},nr={class:"operation-stage__meta"},sr={class:"operation-stage__label"},lr={class:"operation-stage__hint"},ur={class:"operation-stage__canvas"},pr={class:"operation-panel"},dr={class:"operation-panel__scroll"},cr={class:"editor-section"},vr={class:"editor-section"},mr={class:"editor-section"},fr={class:"editor-section"},_r={class:"editor-section__title operation-panel__title-row"},gr={key:1,class:"hotspot-rail hotspot-rail--panel"},br=["onClick","onKeydown"],yr={class:"hotspot-rail-item__top"},xr={class:"hotspot-rail-item__title"},kr={class:"hotspot-rail-item__index"},wr=["src"],hr={class:"hotspot-rail-item__badges"},Sr={class:"hotspot-rail-item__meta"},Cr={class:"hotspot-rail-item__footer"},Nr={class:"hotspot-rail-item__actions"},Hr={key:0,class:"editor-section"},Pr={class:"hotspot-detail-head"},zr={class:"hotspot-detail-head__main"},$r=["src"],jr={class:"hotspot-detail-head__content"},Ur={class:"editor-section__title operation-panel__title-row"},Tr={class:"hotspot-detail-head__desc"},Dr={class:"action-mode-grid action-mode-grid--panel"},Mr=["onClick"],Fr={class:"action-mode-card__title"},qr={class:"action-mode-card__desc"},Ar={class:"position-input-group"},Er={class:"direction-icon-grid"},Ir=["onClick"],Br={class:"direction-icon-item__label"},Vr={class:"hotspot-custom-icon"},Kr={class:"hotspot-custom-icon__preview"},Lr=["src"],Or={key:1},Rr={class:"hotspot-custom-icon__actions"},Jr={class:"direction-size-control"},Wr={key:1,class:"operation-panel-empty"},Yr={class:"position-picker-modal"},Xr={class:"position-picker-modal__toolbar"},Gr={class:"position-picker-modal__footer"},Qr={class:"position-picker-modal__footer-text"},Zr="/panorama_h5/images/guide",ei=42,dt=36,ct=96,vt=44,ti=760,ai=420,oi=Lt({__name:"scene-edit",setup(mt){const ze=Ba(),Ve=Va(),l=C(!1),R=C(!1),ne=C([]),J=C([]),se=C([]),G=C(),L=C(null),le=C(null),E=C(""),Q=C(""),M=C("basic"),F=C(0),D=C("assign"),m=C("marker"),W=C(!1),I=C(""),P=C({id:0,title:"",code:"",cover:"",description:"",adapter_type:"equirectangular",entry_scene_key:"",min_fov:30,max_fov:90,show_title:1,status:1,sort:0,view_count:0,scene_count:0,hotspot_count:0,scenes:[]}),Ye={scene:"点击后切换到另一个场景，适合做主导览链路。",info:"点击后弹出说明内容，用于景点介绍或提示。",link:"点击后跳到外部 H5 地址或系统内链接。",business:"点击后进入业务页面，并记录业务标识。"},Xe={scene:"blue",info:"gold",link:"purple",business:"green"},ge=[{value:"up_left",asset:"front-left",frameIndex:0,label:"左前"},{value:"up",asset:"front",frameIndex:0,label:"正前"},{value:"up_right",asset:"front-right",frameIndex:0,label:"右前"},{value:"down_left",asset:"front-left",frameIndex:5,label:"左后"},{value:"down",asset:"front",frameIndex:4,label:"正后"},{value:"down_right",asset:"front-right",frameIndex:5,label:"右后"}],$e=[{key:"view",label:"视角",short:"视",description:"拖动画面后确认初始视角"},{key:"audio",label:"讲解",short:"讲",description:"维护讲解文案与后续音频扩展"},{key:"compass",label:"指北针",short:"北",description:"校准视角方向与方位感"},{key:"marker",label:"标记",short:"标",description:"热点列表、动作和落点编辑"}],Z=()=>({fullWidth:"",fullHeight:"",croppedHeight:"",croppedWidth:"",croppedX:"",croppedY:"",poseHeading:"",posePitch:"",poseRoll:""}),be=()=>({left:"",front:"",right:"",back:"",top:"",bottom:""}),Ge=(t="equirectangular")=>t==="equirectangular_tiles"?{resolution:64,panorama:{baseUrl:"",tileUrl:"",width:"",cols:"",rows:""},baseBlur:0,showErrorTile:1,status:1,panoData:Z()}:t==="cubemap"?{panorama:{type:"net"},net:"",stripe:"",separate:be(),flipTopBottom:0}:{resolution:64,panorama:"",status:0,panoData:Z()},B=(t,e)=>{const i=e&&typeof e=="object"?JSON.parse(JSON.stringify(e)):{};if(t==="equirectangular_tiles"){const d=typeof i.panorama=="object"&&i.panorama?i.panorama:{};return{...i,resolution:Number(i.resolution||64),panorama:{baseUrl:String(d.baseUrl||""),tileUrl:String(d.tileUrl||""),width:d.width??"",cols:d.cols??"",rows:d.rows??""},baseBlur:Number(i.baseBlur??0),showErrorTile:Number(i.showErrorTile??1),status:Number(i.status??1),panoData:{...Z(),...i.panoData||{}}}}if(t==="cubemap"){const d=typeof i.panorama=="object"&&i.panorama?i.panorama:{},y=["net","stripe","separate"].includes(String(d.type||""))?String(d.type):"net";return{...i,panorama:{...d,type:y},net:String(i.net||""),stripe:String(i.stripe||""),separate:{...be(),...i.separate||{}},flipTopBottom:Number(i.flipTopBottom??0)}}return{...i,resolution:Number(i.resolution||64),panorama:typeof i.panorama=="string"?i.panorama:"",status:Number(i.status??0),panoData:{...Z(),...i.panoData||{}}}},je=(t,e,i="")=>{const d=String(i||"").trim(),y=e&&typeof e=="object"?e:{};if(t==="equirectangular_tiles"){const h=typeof y.panorama=="object"&&y.panorama?y.panorama:{};return String(h.tileUrl||d).trim()}if(t==="cubemap"){const h=typeof y.panorama=="object"&&y.panorama?y.panorama:{},g=String(h.type||"net");if(g==="stripe")return String(y.stripe||d).trim();if(g==="separate"){const w=y.separate||{};return String(w.front||w.left||w.right||w.back||w.top||w.bottom||d).trim()}return String(y.net||d).trim()}return typeof y.panorama=="string"&&String(y.panorama).trim()?String(y.panorama).trim():d},ee=(t="")=>({id:0,hotspot_key:`hotspot_${Date.now()}_${Math.random().toString(16).slice(2,6)}`,name:t,description:"",position:"",action_type:"scene",target_scene_key:"",action_value:"",business_type:"",business_id:0,status:1,sort:0,ext:{direction_icon:"up",direction_size:vt}}),te=()=>({id:0,scene_key:`scene_${Date.now()}_${Math.random().toString(16).slice(2,6)}`,title:"",caption:"",description:"",thumb:"",panorama_url:"",resolution:64,adapter_config:Ge(),default_zoom_lvl:0,default_yaw:0,default_pitch:0,status:1,sort:0,hotspots:[]}),r=C(te()),Qe=b(()=>r.value.id?"编辑场景":"新建场景"),H=b(()=>{const t=String(P.value.adapter_type||"equirectangular");return["equirectangular","equirectangular_tiles","cubemap"].includes(t)?t:"equirectangular"}),O=b(()=>{const t=r.value.adapter_config?.panorama;return typeof t=="object"&&t?String(t.type||"net"):"net"}),Ze=b(()=>H.value==="equirectangular_tiles"?"瓦片地址模板":H.value==="cubemap"?O.value==="stripe"?"条状全景图":O.value==="separate"?"六面图预览来源":"网状全景图":"全景图"),Ue=b(()=>H.value==="equirectangular_tiles"?"保持和原版 tileUrl 一致，支持 [x]、[y] 占位符。":H.value==="cubemap"?O.value==="separate"?"六面图在下方适配参数区逐张配置，当前预览会自动以前景图或首张可用图片生成。":"当前项目使用立方体全景，主资源地址会同步到下方适配参数。":"当前项目使用单张全景图模式，和原版 equirectangular 配置保持一致。"),et=b(()=>M.value==="basic"?"场景配置":"操作工作台"),Te=b(()=>({"--operation-workbench-height":`${ti}px`,"--operation-stage-mobile-height":`${ai}px`})),ue=b(()=>$e.find(t=>t.key===m.value)||$e[0]),Ke=b(()=>D.value==="create"?"gold":"blue"),pe=b(()=>D.value==="create"?"新增落点模式":"当前热点取点"),Le=b(()=>D.value==="create"?"当前为新增模式。直接点击全景画布会新增一个热点并写入该点位，做法与参考项目的取点器保持同一交互方向。":"当前为编辑模式。选中某个热点后，直接点击全景画布即可回写当前热点坐标，也可以点已有图钉切换到对应热点。"),De=b(()=>m.value==="marker"?pe.value:m.value==="view"?"视角取点模式":m.value==="compass"?"方向校准模式":`${ue.value.label}配置`),tt=b(()=>Number.isFinite(Number(r.value.default_yaw))&&Number.isFinite(Number(r.value.default_pitch))),at=b(()=>(r.value.hotspots||[]).filter(t=>!!ht(t.position)).length),p=b(()=>m.value==="marker"?u.value?u.value.position||"点击画布开始取点":"未选择热点":m.value==="view"||m.value==="compass"?tt.value?`已设定 ${Number(r.value.default_yaw||0).toFixed(2)}, ${Number(r.value.default_pitch||0).toFixed(2)}`:"未设定":Re.value||"请先配置全景资源"),_=b(()=>m.value==="marker"?D.value:"assign"),v=b(()=>m.value==="marker"?u.value:null),z=b(()=>m.value==="marker"?u.value?.position||"":m.value==="view"||m.value==="compass"?`${Number(r.value.default_yaw||0)},${Number(r.value.default_pitch||0)}`:""),T=b(()=>m.value==="view"?"默认视角":m.value==="compass"?"方向基准":u.value?.name||""),U=b(()=>m.value==="view"?"view":m.value==="compass"?"compass":m.value==="marker"?"hotspot":"preview"),ye=b(()=>m.value==="marker"?Le.value:m.value==="view"?"拖动画面找到合适视角后，点击中间蓝色按钮把当前视角设为初始位置。":m.value==="compass"?"拖动画面找到正北方向后，点击中间蓝色按钮把当前视角设为正北方。":m.value==="audio"?"讲解区先维护场景说明文案，后续需要时再扩成音频和 TTS。":"基础区维护场景标题、资源和适配参数。"),Oe=b(()=>m.value==="marker"?pe.value:m.value==="view"?"起始视角设定":m.value==="compass"?"方向校准":m.value==="audio"?"讲解预览":"场景基础"),Jt=b(()=>m.value==="marker"?Le.value:m.value==="view"?"当前模式会显示取景框，确认后写入默认偏航角、俯仰角和缩放。":m.value==="compass"?"当前模式会显示取景框，确认后写入当前方向基准参数。":m.value==="audio"?"当前模式仅预览场景全景，右侧维护讲解说明。":"当前模式用于核对场景资源和适配参数。"),Wt=b(()=>m.value==="marker"?D.value==="create"?"当前规则：点击空白区域会新增一个热点并写入该位置；点击已有图钉仍可切换到对应热点。":"当前规则：点击已有图钉可切换热点，点击空白区域可回写当前热点的 yaw,pitch。":m.value==="view"||m.value==="compass"?m.value==="view"?"当前规则：拖动画面调整视角，点击蓝色按钮写入初始视角，不会修改热点坐标。":"当前规则：拖动画面调整方向，点击蓝色按钮写入正北方向，不会修改热点坐标。":"当前规则：画布只做预览，不直接修改热点坐标。"),Yt=b(()=>m.value==="marker"?D.value==="create"?"新增模式：拖动画面后单击落点，会直接创建热点":"编辑模式：拖动画面后单击落点，会更新当前热点坐标":m.value==="view"?"视角模式：拖动画面后点击蓝色按钮确认初始位置":m.value==="compass"?"指北针模式：拖动画面后点击蓝色按钮确认正北方向":"预览模式：当前画布主要用于查看全景资源效果"),Me=b(()=>{const t=[...se.value],e=String(r.value.scene_key||"").trim();return e&&!t.some(i=>String(i.value)===e)&&t.push({label:r.value.title||"当前场景",value:e}),t}),u=b(()=>r.value.hotspots[F.value]||null),ft=b(()=>{const t=u.value;if(!t)return"未选中热点，点击列表或画布中的图标即可进入编辑。";const e=St(t.action_type),i=t.position?.trim()?t.position:"未设置坐标";return`${e} / ${i}`}),de=b(()=>String(I.value||"").trim()),_t=b(()=>de.value||"点击全景画布取点"),Xt=b(()=>{const t=u.value;return t?{...t,position:de.value||t.position}:null}),Re=b(()=>je(H.value,r.value.adapter_config,r.value.panorama_url)),gt=[{key:"fullWidth",label:"全屏宽度"},{key:"fullHeight",label:"全屏高度"},{key:"croppedWidth",label:"裁剪宽度"},{key:"croppedHeight",label:"裁剪高度"},{key:"croppedX",label:"裁剪 X 边距"},{key:"croppedY",label:"裁剪 Y 边距"},{key:"poseHeading",label:"头部角度"},{key:"posePitch",label:"垂直角度"},{key:"poseRoll",label:"旋转角度"}],Gt=[{key:"left",label:"左边图片"},{key:"front",label:"前景图片"},{key:"right",label:"右景图片"},{key:"back",label:"后景图片"},{key:"top",label:"顶部图片"},{key:"bottom",label:"底部图片"}],Fe=()=>Number(Ve.params.projectId||0),ce=t=>{const e=String(t||"").split(".");let i=r.value;for(const d of e)if(i=i?.[d],i==null)return"";return String(i||"")},Qt=(t,e)=>{const i=String(t||"").split(".");let d=r.value;for(let y=0;y<i.length-1;y+=1){const h=i[y];(!d[h]||typeof d[h]!="object")&&(d[h]={}),d=d[h]}d[i[i.length-1]]=e},bt=()=>{r.value.adapter_config=B(H.value,r.value.adapter_config);const t=r.value.adapter_config,e=String(r.value.panorama_url||"").trim();if(H.value==="equirectangular"){t.panorama=e,t.resolution=Number(r.value.resolution||t.resolution||64);return}if(H.value==="equirectangular_tiles"){const y=typeof t.panorama=="object"&&t.panorama?t.panorama:{};t.panorama={...y,tileUrl:e},t.resolution=Number(r.value.resolution||t.resolution||64);return}const i=typeof t.panorama=="object"&&t.panorama?t.panorama:{type:"net"},d=String(i.type||"net");if(t.panorama={...i,type:d},d==="stripe"){t.stripe=e;return}if(d==="separate"){const y={...be(),...t.separate||{}};e&&!String(y.front||"").trim()&&(y.front=e),t.separate=y;return}t.net=e},xe=(t=!1)=>{r.value.adapter_config=B(H.value,r.value.adapter_config);const e=je(H.value,r.value.adapter_config,r.value.panorama_url);(t||H.value!=="equirectangular"||!String(r.value.panorama_url||"").trim())&&(r.value.panorama_url=e)},yt=()=>{r.value.adapter_config=B(H.value,r.value.adapter_config),r.value.resolution=Number(r.value.adapter_config?.resolution||r.value.resolution||64),bt(),xe(!0)},ve=t=>r.value.adapter_config?.[t]??"",me=(t,e)=>{r.value.adapter_config=B(H.value,r.value.adapter_config),r.value.adapter_config[t]=e,t==="resolution"&&(r.value.resolution=Number(e||64)),(t==="net"||t==="stripe"||t==="separate")&&xe(!0)},qe=t=>(typeof r.value.adapter_config?.panorama=="object"&&r.value.adapter_config?.panorama?r.value.adapter_config.panorama:{})[t]??"",Ae=(t,e)=>{r.value.adapter_config=B(H.value,r.value.adapter_config);const i=typeof r.value.adapter_config?.panorama=="object"&&r.value.adapter_config?.panorama?{...r.value.adapter_config.panorama}:{};i[t]=e,r.value.adapter_config.panorama=i,(t==="tileUrl"||t==="type")&&xe(!0)},xt=t=>(r.value.adapter_config?.panoData||{})[t]??"",kt=(t,e)=>{r.value.adapter_config=B(H.value,r.value.adapter_config),r.value.adapter_config.panoData={...Z(),...r.value.adapter_config.panoData||{},[t]:e}},wt=t=>(r.value.adapter_config?.separate||{})[t]??"",ht=t=>{const e=String(t||"").trim();if(!e)return null;const[i="",d=""]=e.split(","),y=Number(i),h=Number(d);return!Number.isFinite(y)||!Number.isFinite(h)?null:{yaw:y,pitch:h}},ae=(t,e,i)=>Number.isFinite(t)?Math.min(Math.max(t,e),i):0,Zt=t=>Ye[String(t)]||"未定义的动作模式",St=t=>ne.value.find(e=>String(e.value)===String(t||""))?.label||"未设置",ea=t=>Xe[String(t||"")]||"default",Ee=t=>{const e=Pt(t||ee());return ge.find(d=>d.value===e)||ge[1]},ta=t=>String(t.action_type||"")==="scene"?`目标：${Me.value.find(i=>String(i.value)===String(t.target_scene_key||""))?.label||t.target_scene_key||"未选择"}`:String(t.action_type||"")==="info"?t.action_value?.trim()||t.description?.trim()||"说明内容未填写":String(t.action_type||"")==="business"?t.action_value?.trim()||`业务类型：${t.business_type||"未设置"}`:t.action_value?.trim()||"跳转地址未填写",ot=t=>r.value.hotspots.findIndex(e=>String(e.hotspot_key||"").trim()===String(t||"").trim()),rt=t=>{const e=r.value.hotspots||[];if(!e.length){F.value=0;return}const i=typeof t=="number"?t:F.value;F.value=Math.min(Math.max(i,0),e.length-1)},aa=(t="visual")=>{if(t==="picker"){le.value?.focusActiveHotspot();return}L.value?.focusActiveHotspot()},Y=(t,e={})=>{rt(t),D.value="assign",e.focusCanvas&&X(()=>{aa(e.focusTarget)})},oa=t=>{r.value={...te(),...t||{},adapter_config:B(H.value,t?.adapter_config),hotspots:(t?.hotspots||[]).map(e=>({...ee(),...e,ext:{...ee().ext||{},...e?.ext||{},direction_icon:st(String(e?.ext&&typeof e.ext=="object"&&"direction_icon"in e.ext?e.ext.direction_icon||"":"up")),direction_size:Je(e?.ext&&typeof e.ext=="object"&&"direction_size"in e.ext?e.ext.direction_size:vt)}}))},r.value.resolution=Number(r.value.adapter_config?.resolution||r.value.resolution||64),xe(),D.value="assign",rt(0)},it=async()=>{const t=Fe();if(t<=0){$.error("缺少项目ID"),ze.push({name:"PanoramaProjects"});return}l.value=!0;try{const e=Number(Ve.params.id||0),i=await Oa({project_id:t,id:e>0?e:0});i.status===1&&i.data?(P.value={...P.value,...i.data.project_info||{}},ne.value=i.data.action_type_options||[],J.value=i.data.bind_type_options||[],se.value=i.data.scene_options||[],oa(i.data.info)):$.error(i.msg||"获取场景详情失败")}catch{$.error("获取场景详情失败")}finally{l.value=!1}},Ct=()=>{ze.push({name:"PanoramaSceneList",params:{projectId:String(Fe())}})},ra=()=>{if(!r.value.preview_url){$.warning("请先保存场景再预览");return}window.open(r.value.preview_url,"_blank","noopener,noreferrer")},ia=async()=>{if(!r.value.id){$.warning("请先保存场景");return}try{const t=await Ra({project_id:Fe(),scene_id:r.value.id});t.status===1?($.success("默认入口场景已更新"),it()):$.error(t.msg||"设置失败")}catch{$.error("设置失败")}},fe=(t={},e={})=>{const i=r.value.hotspots.reduce((y,h)=>Math.max(y,Number(h.sort||0)),0),d={...ee(`热点 ${r.value.hotspots.length+1}`),...t};return d.target_scene_key=String(d.target_scene_key||Me.value[0]?.value||r.value.scene_key||""),d.sort=Number(d.sort||i+1),r.value.hotspots.push(d),Y(r.value.hotspots.length-1,e),d},nt=()=>{r.value.hotspots.forEach((t,e)=>{t.sort=e+1})},na=t=>{r.value.hotspots.splice(t,1),nt(),rt(t)},Nt=t=>{const e=r.value.hotspots[t];if(!e)return;const i=JSON.parse(JSON.stringify(e)),d=String(e.name||`热点 ${t+1}`).trim()||`热点 ${t+1}`,y={...i,...ee(`${d} 副本`),name:`${d} 副本`,description:i.description||"",position:i.position||"",action_type:i.action_type||"scene",target_scene_key:i.target_scene_key||"",action_value:i.action_value||"",business_type:i.business_type||"",business_id:Number(i.business_id||0),status:Number(i.status??1),sort:t+2,ext:i.ext||{},id:0};y.target_scene_key=String(y.target_scene_key||Me.value[0]?.value||r.value.scene_key||""),r.value.hotspots.splice(t+1,0,y),nt(),Y(t+1,{focusCanvas:!!y.position})},Ht=(t,e)=>{const i=e==="up"?t-1:t+1;if(i<0||i>=r.value.hotspots.length)return;const[d]=r.value.hotspots.splice(t,1);d&&(r.value.hotspots.splice(i,0,d),nt(),Y(i,{focusCanvas:!!d.position}))},sa=(t,e)=>{t.action_type=String(e),t.action_type==="scene"&&!t.target_scene_key&&(t.target_scene_key=String(Me.value[0]?.value||r.value.scene_key||""))},st=t=>{const e=String(t||"").trim();return e?e==="left"?"up_left":e==="right"?"up_right":e:"up"},Pt=t=>{const e=t?.ext&&typeof t.ext=="object"?t.ext:{};return st(String(e.direction_icon||"").trim())},oe=t=>{const e=t?.ext&&typeof t.ext=="object"?t.ext:{};return String(e.custom_icon||"").trim()},Je=t=>{const e=Number(t);return Number.isFinite(e)?Math.min(ct,Math.max(dt,Math.round(e))):vt},Ie=t=>{const e=t?.ext&&typeof t.ext=="object"?t.ext:{};return Je(e.direction_size)},la=(t,e,i={})=>{(!t.ext||typeof t.ext!="object")&&(t.ext={}),t.ext.direction_icon=st(String(e||"up")),i.focusCanvas&&t.position?.trim()&&X(()=>{L.value?.focusActiveHotspot()})},zt=(t,e)=>{(!t.ext||typeof t.ext!="object")&&(t.ext={}),t.ext.direction_size=Je(e)},$t=(t,e,i={})=>{const d=String(e||"").trim();(!t.ext||typeof t.ext!="object")&&(t.ext={}),t.ext.custom_icon=d,i.focusCanvas&&t.position?.trim()&&X(()=>{L.value?.focusActiveHotspot()})},ua=(t,e={})=>{$t(t,"",e)},lt=(t,e=0,i=ei)=>{const y=t==="front"?5:6,h=Je(i);return{backgroundImage:`url(${Zr}/${t}.png)`,backgroundRepeat:"no-repeat",width:`${h}px`,height:`${h}px`,backgroundSize:`${h}px ${y*h}px`,backgroundPosition:`center -${Math.max(0,e)*h}px`}},ke=t=>{E.value=t,Q.value="",G.value?.open({multiple:!1,maxCount:1,title:t==="thumb"?"选择缩略图":"选择图片",value:ce(t)})},pa=t=>{E.value="hotspot_custom_icon",Q.value=String(t.hotspot_key||""),G.value?.open({multiple:!1,maxCount:1,title:"选择热点图标",mode:"mixed",defaultTab:"image",enableIconColor:!0,iconBehavior:"upload-image",value:oe(t)})},da=t=>{const e=String(t?.[0]?.url||"").trim();if(!(!e||!E.value)){if(E.value==="hotspot_custom_icon"){const i=r.value.hotspots.find(d=>String(d.hotspot_key||"")===Q.value)||u.value;i&&$t(i,e,{focusCanvas:!0}),E.value="",Q.value="";return}Qt(E.value,e),E.value==="panorama_url"&&!r.value.thumb&&(r.value.thumb=e),String(E.value).startsWith("adapter_config.")?xe(!0):E.value==="panorama_url"&&bt(),E.value="",Q.value=""}},We=()=>(yt(),Re.value?!0:($.warning("请先选择全景图"),!1)),ca=t=>{if(M.value="workbench",m.value=t,!We()){M.value="basic",m.value="marker";return}t==="marker"&&Ut()},jt=()=>{We()&&(M.value="workbench",m.value="marker",D.value="create")},Ut=()=>{We()&&(u.value||fe({},{focusCanvas:!1}),M.value="workbench",m.value="marker",D.value="assign",u.value?.position?.trim()&&X(()=>{L.value?.focusActiveHotspot()}))},va=t=>{const e=ht(t);e&&(r.value.default_yaw=ae(e.yaw,-180,180),r.value.default_pitch=ae(e.pitch,-90,90))},ma=t=>{r.value.default_yaw=ae(Number(t.yaw||0),-180,180),r.value.default_pitch=ae(Number(t.pitch||0),-90,90),t.zoom!==null&&(r.value.default_zoom_lvl=Math.min(Math.max(Number(t.zoom||0),0),100))},fa=()=>{r.value.default_yaw=ae(Number(P.value.start_yaw??0),-180,180),r.value.default_pitch=ae(Number(P.value.start_pitch??0),-90,90),r.value.default_zoom_lvl=Math.min(Math.max(Number(P.value.start_zoom_lvl??0),0),100)},_a=()=>{r.value.default_yaw=ae(Number(P.value.start_yaw??0),-180,180),r.value.default_pitch=ae(Number(P.value.start_pitch??0),-90,90)},Tt=()=>{if(!u.value?.position?.trim()){$.warning("当前热点还没有坐标");return}L.value?.focusActiveHotspot()},ga=()=>{if(!de.value){$.warning("当前还没有待写入坐标");return}le.value?.focusActiveHotspot()},Dt=()=>{We()&&(u.value||fe({},{focusCanvas:!1}),I.value=String(u.value?.position||""),W.value=!0,X(()=>{de.value&&le.value?.focusActiveHotspot()}))},ba=t=>{Y(t),X(()=>{Dt()})},ya=()=>{W.value=!1},xa=()=>{I.value=String(u.value?.position||"")},ka=t=>{const i=(r.value.hotspots||[]).length;if(!i)return-1;const d=Math.min(Math.max(F.value,0),i-1);return i===1?d:t==="prev"?(d-1+i)%i:(d+1)%i},Mt=(t,e={})=>{const i=ka(t);i<0||(e.syncPickerDraft&&(I.value=String(r.value.hotspots[i]?.position||"")),Y(i,{focusCanvas:e.focusCanvas,focusTarget:e.focusTarget}))},wa=()=>{if(!u.value){$.warning("当前没有可写入的热点");return}if(!de.value){$.warning("请先点击全景画布选择位置");return}u.value.position=de.value,W.value=!1,X(()=>{L.value?.focusActiveHotspot()})},ha=t=>{const e=String(t||"").trim();if(!e)return;if(m.value==="view"||m.value==="compass"){va(e);return}if(m.value!=="marker")return;if(D.value==="create"){fe({position:e},{focusCanvas:!1}),D.value="assign",X(()=>{L.value?.focusActiveHotspot()});return}u.value||fe({},{focusCanvas:!1});const i=u.value||r.value.hotspots[r.value.hotspots.length-1];i&&(i.position=e,D.value="assign")},ut=(t,e={})=>{const i=ot(t);i<0||(e.syncPickerDraft&&(I.value=String(r.value.hotspots[i]?.position||"")),Y(i,{focusCanvas:e.focusCanvas}))},Sa=t=>{m.value==="marker"&&ut(t,{focusCanvas:!0})},Ca=t=>{m.value==="marker"&&Mt(t,{focusCanvas:!0,focusTarget:"visual"})},Na=()=>{m.value!=="marker"&&(m.value="marker"),jt()},Ha=t=>{m.value!=="view"&&m.value!=="compass"||ma(t)},Pa=t=>{if(m.value!=="marker")return;const e=String(t.hotspotKey||"").trim(),i=String(t.position||"").trim();if(!e||!i)return;const d=ot(e);if(d<0)return;d!==F.value&&Y(d);const y=r.value.hotspots[d];y&&(y.position=i,D.value="assign")},za=t=>{I.value=String(t||"").trim()},$a=t=>{const e=String(t.hotspotKey||"").trim(),i=String(t.position||"").trim();!e||!i||ot(e)<0||(ut(e,{syncPickerDraft:!1}),I.value=i)},ja=t=>{ut(t,{syncPickerDraft:!0})},Ua=t=>{Mt(t,{focusCanvas:!0,focusTarget:"picker",syncPickerDraft:!0})},Ta=()=>{M.value!=="workbench"||m.value!=="marker"||u.value?.position?.trim()&&X(()=>{L.value?.focusActiveHotspot()})};He(()=>[M.value,m.value],()=>{Ta()}),He(H,t=>{r.value.adapter_config=B(t,r.value.adapter_config),r.value.resolution=Number(r.value.adapter_config?.resolution||r.value.resolution||64),xe()},{immediate:!0});const Da=async()=>{if(yt(),!r.value.title.trim()){$.warning("请填写场景名称");return}if(!Re.value){$.warning(H.value==="equirectangular_tiles"?"请填写瓦片地址模板":"请选择全景图");return}for(const t of r.value.hotspots){if(!t.name.trim()){$.warning("存在未填写名称的热点");return}if(!t.position.trim()){$.warning(`热点「${t.name}」缺少位置`);return}if(t.action_type==="scene"&&!t.target_scene_key){$.warning(`热点「${t.name}」请选择目标场景`);return}if((t.action_type==="link"||t.action_type==="business")&&!t.action_value.trim()){$.warning(`热点「${t.name}」请填写跳转地址`);return}if(t.action_type==="info"&&!t.action_value.trim()&&!t.description.trim()){$.warning(`热点「${t.name}」请填写说明内容`);return}}R.value=!0;try{const t=JSON.parse(JSON.stringify(r.value));t.adapter_config=B(H.value,t.adapter_config),t.panorama_url=je(H.value,t.adapter_config,t.panorama_url),t.resolution=Number(t.adapter_config?.resolution||t.resolution||64);const e=await Ja({project_id:Fe(),info:t});e.status===1&&e.data?($.success("保存成功"),!r.value.id&&e.data.id&&await ze.replace({name:"PanoramaSceneEdit",params:{projectId:String(Fe()),id:String(e.data.id)}}),await it()):$.error(e.msg||"保存失败")}catch{$.error("保存失败")}finally{R.value=!1}};return Ot(()=>{it()}),(t,e)=>{const i=N("a-button"),d=N("a-space"),y=N("a-alert"),h=N("a-input"),g=N("a-form-item"),w=N("a-col"),we=N("a-row"),V=N("a-input-number"),q=N("a-radio"),re=N("a-radio-group"),he=N("a-image"),pt=N("a-textarea"),Se=N("a-form"),Ft=N("a-tab-pane"),ie=N("a-tag"),qt=N("a-empty"),Ma=N("a-popconfirm"),At=N("a-select"),Fa=N("a-slider"),qa=N("a-tabs"),Aa=N("a-spin"),Ea=N("a-modal");return c(),k("div",eo,[a(Ka,{ref_key:"imageSelectorRef",ref:G,multiple:!1,"max-count":1,onChange:da},null,512),a(La,null,{title:o(()=>[a(d,null,{default:o(()=>[a(i,{type:"link",class:"back-btn",onClick:Ct},{default:o(()=>[...e[50]||(e[50]=[f("返回",-1)])]),_:1}),s("span",null,x(Qe.value),1)]),_:1})]),description:o(()=>[...e[51]||(e[51]=[f(" 当前页只维护一个全景场景。基础资源和适配参数放在“场景配置”，需要在画布里完成的视角、指北针和热点操作统一放到“操作工作台”，避免一页里同时堆资源参数和交互动作。 ",-1)])]),actions:o(()=>[a(d,{wrap:""},{default:o(()=>[a(i,{onClick:Ct},{default:o(()=>[...e[52]||(e[52]=[f("返回场景列表",-1)])]),_:1}),r.value.id&&r.value.preview_url?(c(),S(i,{key:0,onClick:ra},{default:o(()=>[...e[53]||(e[53]=[f("预览",-1)])]),_:1})):A("",!0),r.value.id?(c(),S(i,{key:1,type:"dashed",onClick:ia,disabled:P.value.entry_scene_id===r.value.id},{default:o(()=>[f(x(P.value.entry_scene_id===r.value.id?"当前默认入口":"设为默认入口"),1)]),_:1},8,["disabled"])):A("",!0)]),_:1})]),footer:o(()=>[s("div",to,[s("div",ao,[e[54]||(e[54]=s("span",{class:"page-savebar__label"},"当前分组",-1)),s("strong",oo,x(et.value),1),e[55]||(e[55]=s("span",{class:"page-savebar__hint"},"修改后统一保存，未提交内容不会写入当前场景配置。",-1))]),a(d,null,{default:o(()=>[a(i,{type:"primary",size:"large",loading:R.value,onClick:Da},{default:o(()=>[...e[56]||(e[56]=[f("保存设置",-1)])]),_:1},8,["loading"])]),_:1})])]),default:o(()=>[a(Aa,{spinning:l.value},{default:o(()=>[a(y,{class:"tip-alert",type:"info","show-icon":"",message:"场景说明",description:"当前页只维护一个场景。项目与场景已拆开，热点、视角、指北针与资源参数都在当前页闭环完成。"}),s("div",ro,[s("div",io,[e[57]||(e[57]=s("div",{class:"summary-item__label"},"所属项目",-1)),s("div",no,x(P.value.title||"-"),1)]),s("div",so,[e[58]||(e[58]=s("div",{class:"summary-item__label"},"项目类型",-1)),s("div",lo,x(P.value.adapter_type_text||"-"),1)]),s("div",uo,[e[59]||(e[59]=s("div",{class:"summary-item__label"},"热点数量",-1)),s("div",po,x(r.value.hotspots.length),1)]),s("div",co,[e[60]||(e[60]=s("div",{class:"summary-item__label"},"默认入口",-1)),s("div",vo,x(P.value.entry_scene_id===r.value.id&&r.value.id?"当前场景":P.value.entry_scene_title||"未设置"),1)])]),a(qa,{activeKey:M.value,"onUpdate:activeKey":e[48]||(e[48]=n=>M.value=n),class:"scene-edit-tabs"},{default:o(()=>[a(Ft,{key:"basic",tab:"场景配置"},{default:o(()=>[a(Bt,{title:"场景资料",description:"先维护场景名称、缩略图、资源来源和基础说明，再进入工作台做交互编辑。"},{default:o(()=>[a(Se,{layout:"vertical"},{default:o(()=>[a(we,{gutter:24},{default:o(()=>[a(w,{xs:24,lg:12},{default:o(()=>[a(g,{label:"场景名称",required:""},{default:o(()=>[a(h,{value:r.value.title,"onUpdate:value":e[0]||(e[0]=n=>r.value.title=n),maxlength:"120",placeholder:"请输入场景名称"},null,8,["value"])]),_:1})]),_:1}),a(w,{xs:24,lg:12},{default:o(()=>[a(g,{label:"显示标题"},{default:o(()=>[a(h,{value:r.value.caption,"onUpdate:value":e[1]||(e[1]=n=>r.value.caption=n),maxlength:"120",placeholder:"留空时默认使用场景名称"},null,8,["value"])]),_:1})]),_:1})]),_:1}),a(we,{gutter:24},{default:o(()=>[a(w,{xs:24,lg:8},{default:o(()=>[a(g,{label:"场景键"},{default:o(()=>[a(h,{value:r.value.scene_key,"onUpdate:value":e[2]||(e[2]=n=>r.value.scene_key=n),maxlength:"80",placeholder:"留空自动生成"},null,8,["value"])]),_:1})]),_:1}),a(w,{xs:24,lg:8},{default:o(()=>[a(g,{label:"排序"},{default:o(()=>[a(V,{value:r.value.sort,"onUpdate:value":e[3]||(e[3]=n=>r.value.sort=n),style:{width:"100%"}},null,8,["value"])]),_:1})]),_:1}),a(w,{xs:24,lg:8},{default:o(()=>[a(g,{label:"状态"},{default:o(()=>[a(re,{value:r.value.status,"onUpdate:value":e[4]||(e[4]=n=>r.value.status=n)},{default:o(()=>[a(q,{value:1},{default:o(()=>[...e[61]||(e[61]=[f("启用",-1)])]),_:1}),a(q,{value:0},{default:o(()=>[...e[62]||(e[62]=[f("停用",-1)])]),_:1})]),_:1},8,["value"])]),_:1})]),_:1})]),_:1}),a(we,{gutter:24},{default:o(()=>[a(w,{xs:24,lg:12},{default:o(()=>[a(g,{label:"缩略图"},{default:o(()=>[s("div",mo,[s("div",fo,[r.value.thumb?(c(),S(he,{key:0,src:r.value.thumb,width:"96",height:"96",style:{"object-fit":"cover","border-radius":"12px"}},null,8,["src"])):(c(),k("div",_o,"未选择"))]),s("div",go,[a(i,{onClick:e[5]||(e[5]=n=>ke("thumb"))},{default:o(()=>[...e[63]||(e[63]=[f("选择图片",-1)])]),_:1})])])]),_:1})]),_:1}),a(w,{xs:24,lg:12},{default:o(()=>[a(g,{label:Ze.value,required:""},{default:o(()=>[H.value==="equirectangular"?(c(),k("div",bo,[s("div",yo,[r.value.panorama_url?(c(),S(he,{key:0,src:r.value.panorama_url,width:"96",height:"96",style:{"object-fit":"cover","border-radius":"12px"}},null,8,["src"])):(c(),k("div",xo,"未选择"))]),s("div",ko,[a(i,{onClick:e[6]||(e[6]=n=>ke("panorama_url"))},{default:o(()=>[...e[64]||(e[64]=[f("选择图片",-1)])]),_:1})])])):H.value==="equirectangular_tiles"?(c(),S(h,{key:1,value:String(qe("tileUrl")||""),placeholder:"例如 /uploads/pano/tiles/[x]_[y].jpg","onUpdate:value":e[7]||(e[7]=n=>Ae("tileUrl",n))},null,8,["value"])):(c(),S(y,{key:2,type:"info","show-icon":"",message:"立方体全景资源在下方适配参数维护",description:Ue.value},null,8,["description"])),s("div",wo,x(Ue.value),1)]),_:1},8,["label"])]),_:1})]),_:1}),a(g,{label:"场景说明"},{default:o(()=>[a(pt,{value:r.value.description,"onUpdate:value":e[8]||(e[8]=n=>r.value.description=n),rows:4,maxlength:"500","show-count":""},null,8,["value"])]),_:1})]),_:1})]),_:1}),a(Bt,{title:"全景资源与适配参数",description:"参数结构保持和你提供的 emap Adapter 配置区一致，保存前会自动同步主资源地址。"},{default:o(()=>[a(y,{class:"tip-alert",type:"info","show-icon":"",message:`当前适配模式：${P.value.adapter_type_text||"单张全景图"}`,description:"先把资源来源配置清楚，再进入操作工作台做视角、指北针和热点落点。"},null,8,["message"]),a(Se,{layout:"vertical",class:"adapter-panel"},{default:o(()=>[H.value==="equirectangular"?(c(),S(we,{key:0,gutter:16},{default:o(()=>[a(w,{span:12},{default:o(()=>[a(g,{label:"几何面数"},{default:o(()=>[a(V,{value:Number(ve("resolution")||64),min:1,style:{width:"100%"},"onUpdate:value":e[9]||(e[9]=n=>me("resolution",n))},null,8,["value"])]),_:1})]),_:1}),a(w,{span:12},{default:o(()=>[a(g,{label:"全景剪切"},{default:o(()=>[a(re,{value:Number(ve("status")||0),"onUpdate:value":e[10]||(e[10]=n=>me("status",n))},{default:o(()=>[a(q,{value:0},{default:o(()=>[...e[65]||(e[65]=[f("关闭",-1)])]),_:1}),a(q,{value:1},{default:o(()=>[...e[66]||(e[66]=[f("开启",-1)])]),_:1})]),_:1},8,["value"])]),_:1})]),_:1}),a(w,{span:24},{default:o(()=>[a(g,{label:"当前资源"},{default:o(()=>[a(h,{value:r.value.panorama_url,readonly:""},null,8,["value"])]),_:1})]),_:1}),(c(),k(K,null,_e(gt,n=>a(w,{key:n.key,span:12},{default:o(()=>[a(g,{label:n.label},{default:o(()=>[a(V,{value:xt(n.key),style:{width:"100%"},"onUpdate:value":j=>kt(n.key,j)},null,8,["value","onUpdate:value"])]),_:2},1032,["label"])]),_:2},1024)),64))]),_:1})):H.value==="equirectangular_tiles"?(c(),S(we,{key:1,gutter:16},{default:o(()=>[a(w,{span:24},{default:o(()=>[a(g,{label:"全景小图"},{default:o(()=>[s("div",ho,[s("div",So,[ce("adapter_config.panorama.baseUrl")?(c(),S(he,{key:0,src:ce("adapter_config.panorama.baseUrl"),width:"96",height:"96",style:{"object-fit":"cover","border-radius":"12px"}},null,8,["src"])):(c(),k("div",Co,"未选择"))]),s("div",No,[a(i,{onClick:e[11]||(e[11]=n=>ke("adapter_config.panorama.baseUrl"))},{default:o(()=>[...e[67]||(e[67]=[f("选择图片",-1)])]),_:1})])])]),_:1})]),_:1}),a(w,{span:12},{default:o(()=>[a(g,{label:"几何面数"},{default:o(()=>[a(V,{value:Number(ve("resolution")||64),min:1,style:{width:"100%"},"onUpdate:value":e[12]||(e[12]=n=>me("resolution",n))},null,8,["value"])]),_:1})]),_:1}),a(w,{span:12},{default:o(()=>[a(g,{label:"全景剪切"},{default:o(()=>[a(re,{value:Number(ve("status")||1),"onUpdate:value":e[13]||(e[13]=n=>me("status",n))},{default:o(()=>[a(q,{value:0},{default:o(()=>[...e[68]||(e[68]=[f("关闭",-1)])]),_:1}),a(q,{value:1},{default:o(()=>[...e[69]||(e[69]=[f("开启",-1)])]),_:1})]),_:1},8,["value"])]),_:1})]),_:1}),a(w,{span:12},{default:o(()=>[a(g,{label:"宽度"},{default:o(()=>[a(h,{value:String(qe("width")||""),"onUpdate:value":e[14]||(e[14]=n=>Ae("width",n))},null,8,["value"])]),_:1})]),_:1}),a(w,{span:12},{default:o(()=>[a(g,{label:"列数"},{default:o(()=>[a(h,{value:String(qe("cols")||""),"onUpdate:value":e[15]||(e[15]=n=>Ae("cols",n))},null,8,["value"])]),_:1})]),_:1}),a(w,{span:12},{default:o(()=>[a(g,{label:"行数"},{default:o(()=>[a(h,{value:String(qe("rows")||""),"onUpdate:value":e[16]||(e[16]=n=>Ae("rows",n))},null,8,["value"])]),_:1})]),_:1}),a(w,{span:12},{default:o(()=>[a(g,{label:"瓦片补偿 1px"},{default:o(()=>[a(re,{value:Number(ve("baseBlur")||0),"onUpdate:value":e[17]||(e[17]=n=>me("baseBlur",n))},{default:o(()=>[a(q,{value:0},{default:o(()=>[...e[70]||(e[70]=[f("关闭",-1)])]),_:1}),a(q,{value:1},{default:o(()=>[...e[71]||(e[71]=[f("开启",-1)])]),_:1})]),_:1},8,["value"])]),_:1})]),_:1}),a(w,{span:12},{default:o(()=>[a(g,{label:"显示错误瓦片"},{default:o(()=>[a(re,{value:Number(ve("showErrorTile")||1),"onUpdate:value":e[18]||(e[18]=n=>me("showErrorTile",n))},{default:o(()=>[a(q,{value:0},{default:o(()=>[...e[72]||(e[72]=[f("关闭",-1)])]),_:1}),a(q,{value:1},{default:o(()=>[...e[73]||(e[73]=[f("开启",-1)])]),_:1})]),_:1},8,["value"])]),_:1})]),_:1}),a(w,{span:12},{default:o(()=>[a(g,{label:"当前 tileUrl"},{default:o(()=>[a(h,{value:String(qe("tileUrl")||""),readonly:""},null,8,["value"])]),_:1})]),_:1}),(c(),k(K,null,_e(gt,n=>a(w,{key:n.key,span:12},{default:o(()=>[a(g,{label:n.label},{default:o(()=>[a(V,{value:xt(n.key),style:{width:"100%"},"onUpdate:value":j=>kt(n.key,j)},null,8,["value","onUpdate:value"])]),_:2},1032,["label"])]),_:2},1024)),64))]),_:1})):(c(),S(we,{key:2,gutter:16},{default:o(()=>[a(w,{span:24},{default:o(()=>[a(g,{label:"图片模式"},{default:o(()=>[a(re,{value:O.value,"onUpdate:value":e[19]||(e[19]=n=>Ae("type",n))},{default:o(()=>[a(q,{value:"net"},{default:o(()=>[...e[74]||(e[74]=[f("网状模式",-1)])]),_:1}),a(q,{value:"stripe"},{default:o(()=>[...e[75]||(e[75]=[f("条状模式",-1)])]),_:1}),a(q,{value:"separate"},{default:o(()=>[...e[76]||(e[76]=[f("分离模式",-1)])]),_:1})]),_:1},8,["value"])]),_:1})]),_:1}),a(w,{span:12},{default:o(()=>[a(g,{label:"改变顶底"},{default:o(()=>[a(re,{value:Number(ve("flipTopBottom")||0),"onUpdate:value":e[20]||(e[20]=n=>me("flipTopBottom",n))},{default:o(()=>[a(q,{value:0},{default:o(()=>[...e[77]||(e[77]=[f("关闭",-1)])]),_:1}),a(q,{value:1},{default:o(()=>[...e[78]||(e[78]=[f("开启",-1)])]),_:1})]),_:1},8,["value"])]),_:1})]),_:1}),a(w,{span:12},{default:o(()=>[a(g,{label:"当前预览来源"},{default:o(()=>[a(h,{value:Re.value,readonly:""},null,8,["value"])]),_:1})]),_:1}),O.value==="net"?(c(),S(w,{key:0,span:24},{default:o(()=>[a(g,{label:"网状图片",required:""},{default:o(()=>[s("div",Ho,[s("div",Po,[ce("adapter_config.net")?(c(),S(he,{key:0,src:ce("adapter_config.net"),width:"96",height:"96",style:{"object-fit":"cover","border-radius":"12px"}},null,8,["src"])):(c(),k("div",zo,"未选择"))]),s("div",$o,[a(i,{onClick:e[21]||(e[21]=n=>ke("adapter_config.net"))},{default:o(()=>[...e[79]||(e[79]=[f("选择网状图",-1)])]),_:1})])])]),_:1})]),_:1})):O.value==="stripe"?(c(),S(w,{key:1,span:24},{default:o(()=>[a(g,{label:"条状图片",required:""},{default:o(()=>[s("div",jo,[s("div",Uo,[ce("adapter_config.stripe")?(c(),S(he,{key:0,src:ce("adapter_config.stripe"),width:"96",height:"96",style:{"object-fit":"cover","border-radius":"12px"}},null,8,["src"])):(c(),k("div",To,"未选择"))]),s("div",Do,[a(i,{onClick:e[22]||(e[22]=n=>ke("adapter_config.stripe"))},{default:o(()=>[...e[80]||(e[80]=[f("选择条状图",-1)])]),_:1})])])]),_:1})]),_:1})):(c(),k(K,{key:2},[a(w,{span:24},{default:o(()=>[...e[81]||(e[81]=[s("div",{class:"adapter-group-title"},"分离模式六面图",-1)])]),_:1}),(c(),k(K,null,_e(Gt,n=>a(w,{key:n.key,span:24},{default:o(()=>[a(g,{label:n.label,required:""},{default:o(()=>[s("div",Mo,[s("div",Fo,[wt(n.key)?(c(),S(he,{key:0,src:String(wt(n.key)||""),width:"96",height:"96",style:{"object-fit":"cover","border-radius":"12px"}},null,8,["src"])):(c(),k("div",qo,"未选择"))]),s("div",Ao,[a(i,{onClick:j=>ke(`adapter_config.separate.${n.key}`)},{default:o(()=>[...e[82]||(e[82]=[f("选择图片",-1)])]),_:1},8,["onClick"])])])]),_:2},1032,["label"])]),_:2},1024)),64))],64))]),_:1}))]),_:1})]),_:1})]),_:1}),a(Ft,{key:"workbench",tab:"操作工作台"},{default:o(()=>[s("div",Eo,[s("div",Io,[e[83]||(e[83]=s("div",{class:"workbench-overview__label"},"当前工具",-1)),s("div",Bo,x(ue.value.label),1),s("div",Vo,x(De.value),1)]),s("div",Ko,[e[84]||(e[84]=s("div",{class:"workbench-overview__label"},"热点定位",-1)),s("div",Lo,x(at.value)+" / "+x(r.value.hotspots.length),1),e[85]||(e[85]=s("div",{class:"workbench-overview__meta"},"已落点热点 / 总热点数",-1))]),s("div",Oo,[e[86]||(e[86]=s("div",{class:"workbench-overview__label"},"当前热点",-1)),s("div",Ro,x(u.value?.name||"未选择热点"),1),s("div",Jo,x(ft.value),1)]),s("div",Wo,[e[87]||(e[87]=s("div",{class:"workbench-overview__label"},"当前状态",-1)),s("div",Yo,x(p.value),1),e[88]||(e[88]=s("div",{class:"workbench-overview__meta"},"当前工作台反馈与关键状态",-1))])]),s("div",Xo,[s("div",Go,[s("div",null,[e[89]||(e[89]=s("div",{class:"operation-shell__eyebrow"},"操作模式",-1)),s("div",Qo,x(r.value.title||"未命名场景"),1),s("div",Zo,x(ue.value.description),1)]),a(d,{wrap:""},{default:o(()=>[a(ie,{color:"blue"},{default:o(()=>[f(x(P.value.adapter_type_text||"全景场景"),1)]),_:1}),a(ie,{color:m.value==="marker"?Ke.value:"cyan"},{default:o(()=>[f(x(De.value),1)]),_:1},8,["color"]),a(ie,null,{default:o(()=>[f(x(p.value),1)]),_:1})]),_:1})]),s("div",{class:"operation-workbench",style:Be(Te.value)},[s("div",er,[(c(),k(K,null,_e($e,n=>s("button",{key:n.key,type:"button",class:Pe(["operation-rail__item",{"is-active":m.value===n.key}]),onClick:j=>ca(n.key)},[s("span",ar,x(n.short),1),s("span",or,x(n.label),1)],10,tr)),64))]),s("div",rr,[s("div",ir,[s("div",nr,[s("div",sr,x(ue.value.label),1),s("div",lr,x(ye.value),1)]),a(d,{wrap:""},{default:o(()=>[m.value==="marker"?(c(),k(K,{key:0},[a(i,{size:"small",onClick:Ut},{default:o(()=>[...e[90]||(e[90]=[f("当前热点取点",-1)])]),_:1}),a(i,{size:"small",onClick:Tt,disabled:!u.value?.position},{default:o(()=>[...e[91]||(e[91]=[f("聚焦当前热点",-1)])]),_:1},8,["disabled"]),a(i,{size:"small",type:"primary",ghost:"",onClick:jt},{default:o(()=>[a(Et(Vt)),e[92]||(e[92]=f(" 新增热点并落点 ",-1))]),_:1})],64)):m.value==="view"?(c(),S(i,{key:1,size:"small",onClick:fa},{default:o(()=>[...e[93]||(e[93]=[f("恢复项目默认视角",-1)])]),_:1})):m.value==="compass"?(c(),S(i,{key:2,size:"small",onClick:_a},{default:o(()=>[...e[94]||(e[94]=[f("恢复项目默认朝向",-1)])]),_:1})):A("",!0)]),_:1})]),s("div",ur,[a(Kt,{ref_key:"visualCanvasRef",ref:L,project:P.value,scene:r.value,"active-hotspot":v.value,"selected-position":z.value,"active-label":T.value,"editor-kind":U.value,"interaction-mode":_.value,"allow-quick-create":m.value==="marker",chrome:!1,height:"100%","mode-label":Oe.value,"mode-description":Jt.value,"footer-text":Wt.value,"editor-hint":Yt.value,onPickPosition:ha,onViewChange:Ha,onSelectHotspot:Sa,onDragHotspot:Pa,onNavigateHotspot:Ca,onRequestCreateHotspot:Na},null,8,["project","scene","active-hotspot","selected-position","active-label","editor-kind","interaction-mode","allow-quick-create","mode-label","mode-description","footer-text","editor-hint"])])]),s("div",pr,[s("div",dr,[m.value==="view"?(c(),k(K,{key:0},[a(y,{class:"tip-alert",type:"info","show-icon":"",message:"起始视角",description:"拖动画面到合适位置后，点击画布中间蓝色按钮，把当前视角设置为进入场景时的初始位置。"}),s("div",cr,[e[95]||(e[95]=s("div",{class:"editor-section__title"},"进入参数",-1)),a(Se,{layout:"vertical"},{default:o(()=>[a(g,{label:"默认缩放"},{default:o(()=>[a(V,{value:r.value.default_zoom_lvl,"onUpdate:value":e[23]||(e[23]=n=>r.value.default_zoom_lvl=n),min:0,max:100,style:{width:"100%"}},null,8,["value"])]),_:1}),a(g,{label:"默认偏航角"},{default:o(()=>[a(V,{value:r.value.default_yaw,"onUpdate:value":e[24]||(e[24]=n=>r.value.default_yaw=n),min:-180,max:180,style:{width:"100%"}},null,8,["value"])]),_:1}),a(g,{label:"默认俯仰角"},{default:o(()=>[a(V,{value:r.value.default_pitch,"onUpdate:value":e[25]||(e[25]=n=>r.value.default_pitch=n),min:-90,max:90,style:{width:"100%"}},null,8,["value"])]),_:1})]),_:1})])],64)):m.value==="audio"?(c(),k(K,{key:1},[a(y,{class:"tip-alert",type:"info","show-icon":"",message:"讲解配置",description:"当前插件还没有独立音频字段，先复用场景说明做讲解文案，界面和工作流先按原版操作区保留。你后面要接音频文件或 TTS，我再补表结构和上传字段。"}),s("div",vr,[e[96]||(e[96]=s("div",{class:"editor-section__title"},"讲解文案",-1)),a(Se,{layout:"vertical"},{default:o(()=>[a(g,{label:"讲解概述"},{default:o(()=>[a(pt,{value:r.value.description,"onUpdate:value":e[26]||(e[26]=n=>r.value.description=n),rows:8,maxlength:"500","show-count":""},null,8,["value"])]),_:1})]),_:1})])],64)):m.value==="compass"?(c(),k(K,{key:2},[a(y,{class:"tip-alert",type:"info","show-icon":"",message:"指北针校准",description:"拖动画面到正北方向后，点击画布中间蓝色按钮“把当前视角设置为正北方”，保存当前方向基准。"}),s("div",mr,[e[97]||(e[97]=s("div",{class:"editor-section__title"},"方向参数",-1)),a(Se,{layout:"vertical"},{default:o(()=>[a(g,{label:"默认偏航角"},{default:o(()=>[a(V,{value:r.value.default_yaw,"onUpdate:value":e[27]||(e[27]=n=>r.value.default_yaw=n),min:-180,max:180,style:{width:"100%"}},null,8,["value"])]),_:1}),a(g,{label:"默认俯仰角"},{default:o(()=>[a(V,{value:r.value.default_pitch,"onUpdate:value":e[28]||(e[28]=n=>r.value.default_pitch=n),min:-90,max:90,style:{width:"100%"}},null,8,["value"])]),_:1})]),_:1})])],64)):(c(),k(K,{key:3},[a(y,{class:"tip-alert",type:"info","show-icon":"",message:"标记编辑",description:"热点动作支持切换场景、说明弹层、外部链接和业务链接。坐标既可在中间全景画布点选，也可手动输入 yaw,pitch。"}),s("div",fr,[s("div",_r,[e[99]||(e[99]=s("span",null,"热点列表",-1)),a(i,{type:"primary",ghost:"",size:"small",onClick:e[29]||(e[29]=n=>fe())},{default:o(()=>[a(Et(Vt)),e[98]||(e[98]=f(" 新增热点 ",-1))]),_:1})]),r.value.hotspots.length===0?(c(),S(qt,{key:0,description:"当前场景还没有热点"},{default:o(()=>[a(i,{type:"primary",onClick:e[30]||(e[30]=n=>fe())},{default:o(()=>[...e[100]||(e[100]=[f("新增第一个热点",-1)])]),_:1})]),_:1})):(c(),k("div",gr,[(c(!0),k(K,null,_e(r.value.hotspots,(n,j)=>(c(),k("div",{key:`hotspot-${j}`,role:"button",tabindex:"0",class:Pe(["hotspot-rail-item",{"is-active":j===F.value}]),onClick:Ce=>Y(j,{focusCanvas:!0}),onKeydown:[It(Ne(Ce=>Y(j,{focusCanvas:!0}),["prevent"]),["enter"]),It(Ne(Ce=>Y(j,{focusCanvas:!0}),["prevent"]),["space"])]},[s("div",yr,[s("div",xr,[s("span",kr,x(j+1),1),oe(n)?(c(),k("img",{key:0,class:"hotspot-rail-item__custom-icon",src:oe(n),alt:"自定义热点图标"},null,8,wr)):n.action_type==="scene"?(c(),k("span",{key:1,class:"hotspot-rail-item__direction",style:Be(lt(Ee(n).asset,Ee(n).frameIndex,Ie(n)))},null,4)):A("",!0),s("span",null,x(n.name||`热点 ${j+1}`),1)]),s("div",hr,[a(ie,{color:ea(n.action_type)},{default:o(()=>[f(x(St(n.action_type)),1)]),_:2},1032,["color"]),n.action_type==="scene"?(c(),S(ie,{key:0,color:"cyan"},{default:o(()=>[f(x(Ee(n).label),1)]),_:2},1024)):A("",!0)])]),s("div",Sr,[s("span",null,x(n.position||"未设置坐标"),1),s("span",null,x(ta(n)),1)]),s("div",Cr,[a(ie,{color:n.status===1?"green":"default"},{default:o(()=>[f(x(n.status===1?"启用":"停用"),1)]),_:2},1032,["color"]),s("div",Nr,[a(i,{size:"small",type:"link",onClick:Ne(Ce=>ba(j),["stop"])},{default:o(()=>[...e[101]||(e[101]=[f("取点",-1)])]),_:1},8,["onClick"]),a(i,{size:"small",type:"link",onClick:Ne(Ce=>Nt(j),["stop"])},{default:o(()=>[...e[102]||(e[102]=[f("复制",-1)])]),_:1},8,["onClick"]),a(i,{size:"small",type:"link",disabled:j===0,onClick:Ne(Ce=>Ht(j,"up"),["stop"])},{default:o(()=>[...e[103]||(e[103]=[f("上移",-1)])]),_:1},8,["disabled","onClick"]),a(i,{size:"small",type:"link",disabled:j===r.value.hotspots.length-1,onClick:Ne(Ce=>Ht(j,"down"),["stop"])},{default:o(()=>[...e[104]||(e[104]=[f(" 下移 ",-1)])]),_:1},8,["disabled","onClick"])])])],42,br))),128))]))]),u.value?(c(),k("div",Hr,[s("div",Pr,[s("div",zr,[u.value.action_type==="scene"&&oe(u.value)?(c(),k("img",{key:0,class:"hotspot-detail-head__custom-icon",src:oe(u.value),alt:"自定义热点图标"},null,8,$r)):u.value.action_type==="scene"?(c(),k("span",{key:1,class:"hotspot-detail-head__sprite",style:Be(lt(Ee(u.value).asset,Ee(u.value).frameIndex,Ie(u.value)))},null,4)):A("",!0),s("div",jr,[s("div",Ur,[s("span",null,x(u.value.name||`热点 ${F.value+1}`),1)]),s("div",Tr,x(ft.value),1)])]),a(d,null,{default:o(()=>[a(i,{size:"small",onClick:e[31]||(e[31]=n=>Nt(F.value))},{default:o(()=>[...e[105]||(e[105]=[f("复制",-1)])]),_:1}),a(Ma,{title:"确定删除该热点吗？",onConfirm:e[32]||(e[32]=n=>na(F.value))},{default:o(()=>[a(i,{size:"small",danger:""},{default:o(()=>[...e[106]||(e[106]=[f("删除",-1)])]),_:1})]),_:1})]),_:1})]),s("div",Dr,[(c(!0),k(K,null,_e(ne.value,n=>(c(),k("button",{key:n.value,type:"button",class:Pe(["action-mode-card",{"is-active":u.value.action_type===String(n.value)}]),onClick:j=>sa(u.value,n.value)},[s("div",Fr,x(n.label),1),s("div",qr,x(Zt(n.value)),1)],10,Mr))),128))]),a(Se,{layout:"vertical",class:"operation-panel__form"},{default:o(()=>[a(g,{label:"热点名称",required:""},{default:o(()=>[a(h,{value:u.value.name,"onUpdate:value":e[33]||(e[33]=n=>u.value.name=n),maxlength:"120"},null,8,["value"])]),_:1}),a(g,{label:"热点键"},{default:o(()=>[a(h,{value:u.value.hotspot_key,"onUpdate:value":e[34]||(e[34]=n=>u.value.hotspot_key=n),maxlength:"80",placeholder:"留空自动生成"},null,8,["value"])]),_:1}),a(g,{label:"位置(yaw,pitch)",required:""},{default:o(()=>[s("div",Ar,[a(h,{value:u.value.position,"onUpdate:value":e[35]||(e[35]=n=>u.value.position=n),placeholder:"例如 0.35,-0.12"},null,8,["value"]),a(i,{onClick:Dt},{default:o(()=>[...e[107]||(e[107]=[f("位置选择",-1)])]),_:1}),a(i,{onClick:Tt,disabled:!u.value.position},{default:o(()=>[...e[108]||(e[108]=[f("聚焦图钉",-1)])]),_:1},8,["disabled"])])]),_:1}),u.value.action_type==="scene"?(c(),S(g,{key:0,label:"目标场景"},{default:o(()=>[a(At,{value:u.value.target_scene_key,"onUpdate:value":e[36]||(e[36]=n=>u.value.target_scene_key=n),options:Me.value,placeholder:"请选择目标场景"},null,8,["value","options"])]),_:1})):A("",!0),u.value.action_type==="scene"?(c(),S(g,{key:1,label:"方向图标"},{default:o(()=>[s("div",Er,[(c(),k(K,null,_e(ge,n=>s("button",{key:n.value,type:"button",class:Pe(["direction-icon-item",{"is-active":Pt(u.value)===n.value}]),onClick:j=>la(u.value,n.value,{focusCanvas:!0})},[s("span",{class:"direction-icon-item__sprite",style:Be(lt(n.asset,n.frameIndex,Ie(u.value)))},null,4),s("span",Br,x(n.label),1)],10,Ir)),64))])]),_:1})):A("",!0),u.value.action_type==="scene"?(c(),S(g,{key:2,label:"自定义图标"},{default:o(()=>[s("div",Vr,[s("div",Kr,[oe(u.value)?(c(),k("img",{key:0,src:oe(u.value),alt:"自定义热点图标"},null,8,Lr)):(c(),k("span",Or,"未设置"))]),s("div",Rr,[a(d,{wrap:""},{default:o(()=>[a(i,{onClick:e[37]||(e[37]=n=>pa(u.value))},{default:o(()=>[...e[109]||(e[109]=[f("选择图标",-1)])]),_:1}),oe(u.value)?(c(),S(i,{key:0,danger:"",onClick:e[38]||(e[38]=n=>ua(u.value,{focusCanvas:!0}))},{default:o(()=>[...e[110]||(e[110]=[f(" 清空 ",-1)])]),_:1})):A("",!0)]),_:1}),e[111]||(e[111]=s("div",{class:"form-help"},"设置后，画布和 H5 预览优先显示该图标；清空后恢复内置方向箭头。",-1))])])]),_:1})):A("",!0),u.value.action_type==="scene"?(c(),S(g,{key:3,label:"方向标尺寸"},{default:o(()=>[s("div",Jr,[a(Fa,{min:dt,max:ct,step:2,value:Ie(u.value),"onUpdate:value":e[39]||(e[39]=n=>zt(u.value,n))},null,8,["value"]),a(V,{min:dt,max:ct,step:2,value:Ie(u.value),style:{width:"92px"},"onUpdate:value":e[40]||(e[40]=n=>zt(u.value,n))},null,8,["value"])]),e[112]||(e[112]=s("div",{class:"form-help"},"拖动画布里的当前热点时，也会保持这里设置的方向标尺寸。",-1))]),_:1})):(c(),S(g,{key:4,label:u.value.action_type==="info"?"说明内容":"跳转地址"},{default:o(()=>[a(h,{value:u.value.action_value,"onUpdate:value":e[41]||(e[41]=n=>u.value.action_value=n),placeholder:u.value.action_type==="info"?"输入提示文案":"输入相对路径或完整 URL"},null,8,["value","placeholder"])]),_:1},8,["label"])),u.value.action_type==="business"?(c(),S(g,{key:5,label:"业务类型"},{default:o(()=>[a(At,{value:u.value.business_type,"onUpdate:value":e[42]||(e[42]=n=>u.value.business_type=n),options:J.value,"allow-clear":"",placeholder:"可选，用于日志识别"},null,8,["value","options"])]),_:1})):A("",!0),u.value.action_type==="business"?(c(),S(g,{key:6,label:"业务ID"},{default:o(()=>[a(V,{value:u.value.business_id,"onUpdate:value":e[43]||(e[43]=n=>u.value.business_id=n),min:0,style:{width:"100%"}},null,8,["value"])]),_:1})):A("",!0),a(g,{label:"排序"},{default:o(()=>[a(V,{value:u.value.sort,"onUpdate:value":e[44]||(e[44]=n=>u.value.sort=n),style:{width:"100%"}},null,8,["value"])]),_:1}),a(g,{label:"状态"},{default:o(()=>[a(re,{value:u.value.status,"onUpdate:value":e[45]||(e[45]=n=>u.value.status=n)},{default:o(()=>[a(q,{value:1},{default:o(()=>[...e[113]||(e[113]=[f("启用",-1)])]),_:1}),a(q,{value:0},{default:o(()=>[...e[114]||(e[114]=[f("停用",-1)])]),_:1})]),_:1},8,["value"])]),_:1}),a(g,{label:"热点描述"},{default:o(()=>[a(pt,{value:u.value.description,"onUpdate:value":e[46]||(e[46]=n=>u.value.description=n),rows:3,maxlength:"500","show-count":""},null,8,["value"])]),_:1})]),_:1})])):(c(),k("div",Wr,[a(qt,{description:"当前还没有选中的热点"},{default:o(()=>[a(i,{type:"primary",onClick:e[47]||(e[47]=n=>fe())},{default:o(()=>[...e[115]||(e[115]=[f("新增热点",-1)])]),_:1})]),_:1})]))],64))])])],4)])]),_:1})]),_:1},8,["activeKey"])]),_:1},8,["spinning"])]),_:1}),a(Ea,{open:W.value,"onUpdate:open":e[49]||(e[49]=n=>W.value=n),title:"位置选择",width:1240,"mask-closable":!1,"destroy-on-close":!1},{footer:o(()=>[s("div",Gr,[s("span",Qr,"当前待写入坐标："+x(_t.value),1),a(d,null,{default:o(()=>[a(i,{onClick:ya},{default:o(()=>[...e[118]||(e[118]=[f("取消",-1)])]),_:1}),a(i,{type:"primary",onClick:wa},{default:o(()=>[...e[119]||(e[119]=[f("确认使用此位置",-1)])]),_:1})]),_:1})])]),default:o(()=>[s("div",Yr,[a(y,{class:"tip-alert",type:"info","show-icon":"",message:"参考原系统的点位选择方式",description:"这里是针对当前热点的专用位置选择弹窗。点击全景画布取点，确认后再回写到当前热点。"}),s("div",Xr,[a(d,{wrap:""},{default:o(()=>[a(ie,{color:"blue"},{default:o(()=>[f(x(u.value?.name||"当前热点"),1)]),_:1}),a(ie,null,{default:o(()=>[f(x(_t.value),1)]),_:1})]),_:1}),a(d,{wrap:""},{default:o(()=>[a(i,{size:"small",onClick:xa},{default:o(()=>[...e[116]||(e[116]=[f("恢复原坐标",-1)])]),_:1}),a(i,{size:"small",onClick:ga,disabled:!de.value},{default:o(()=>[...e[117]||(e[117]=[f("聚焦图钉",-1)])]),_:1},8,["disabled"])]),_:1})]),a(Kt,{ref_key:"positionPickerCanvasRef",ref:le,project:P.value,scene:r.value,"active-hotspot":Xt.value,"allow-quick-create":!1,"interaction-mode":"assign",onPickPosition:za,onSelectHotspot:ja,onDragHotspot:$a,onNavigateHotspot:Ua},null,8,["project","scene","active-hotspot"])])]),_:1},8,["open"])])}}}),xi=Rt(oi,[["__scopeId","data-v-2631991b"]]);export{xi as default};

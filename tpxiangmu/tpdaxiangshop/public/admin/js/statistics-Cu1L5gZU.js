import{w as M}from"./wuxing-DBzTFlJ3.js";import{b5 as $,bE as w,o as T,bg as H,aX as k,h as f}from"./antd-vendor-BXeG7MAg.js";import{d as D,r as O,f as v,o as E,V as i,a1 as L,X as F,k as t,Y as e,u as d,$ as p}from"./vue-vendor-BH3QE-KO.js";import{_ as B}from"./index-CTO9TwcJ.js";import"./utils-vendor-dR2luS_Z.js";import"./charts-vendor-CwCbZZ1s.js";const R={class:"page-container"},S=D({__name:"statistics",setup(j){const n=O({total_test:0,today_test:0,paid_test:0,total_amount:0,element_stats:[],trend_data:[]}),u=v(),_=v(),m=async()=>{try{const s=await M.getStatistics();s.status===1&&s.data?(Object.assign(n,s.data),h()):f.error(s.msg||"获取统计数据失败")}catch{f.error("获取统计数据失败")}},h=()=>{g(),y()},g=()=>{if(!u.value)return;const s=u.value;if(s.innerHTML="",!n.element_stats||n.element_stats.length===0){s.innerHTML='<div class="no-data">暂无数据</div>';return}const l=Math.max(...n.element_stats.map(a=>a.count)),o=`
    <div class="element-chart">
      ${n.element_stats.map(a=>{const r=a.count/l*100,c=x(a.element_type);return`
          <div class="chart-item">
            <div class="chart-label">
              <span class="element-badge" style="background-color: ${c}">${a.element_type}</span>
              <span class="count">${a.count}次</span>
            </div>
            <div class="chart-bar">
              <div class="bar-fill" style="width: ${r}%; background-color: ${c}"></div>
            </div>
          </div>
        `}).join("")}
    </div>
  `;s.innerHTML=o},y=()=>{if(!_.value)return;const s=_.value;if(s.innerHTML="",!n.trend_data||n.trend_data.length===0){s.innerHTML='<div class="no-data">暂无数据</div>';return}const l=Math.max(...n.trend_data.map(a=>a.count)),o=`
    <div class="trend-chart">
      <div class="chart-grid">
        ${n.trend_data.map((a,r)=>{const c=l>0?a.count/l*100:0,C=new Date(a.date).getDate(),b=a.date===new Date().toISOString().split("T")[0];return`
            <div class="chart-column">
              <div class="column-bar" style="height: ${Math.max(c,5)}%">
                <div class="bar-value">${a.count}</div>
              </div>
              <div class="column-label ${b?"today":""}">${C}</div>
            </div>
          `}).join("")}
      </div>
    </div>
  `;s.innerHTML=o},x=s=>({金:"#FFD700",木:"#32CD32",水:"#4169E1",火:"#FF4500",土:"#D2691E"})[s]||"#999999";return E(()=>{m()}),(s,l)=>{const o=i("a-statistic"),a=i("a-card"),r=i("a-col"),c=i("a-row");return F(),L("div",R,[t(a,{bordered:!1,class:"main-card"},{title:e(()=>[t(d(k)),l[0]||(l[0]=p("span",{style:{"margin-left":"8px"}},"数据统计",-1))]),default:e(()=>[t(c,{gutter:16,style:{"margin-bottom":"24px"}},{default:e(()=>[t(r,{span:6},{default:e(()=>[t(a,{class:"stat-card"},{default:e(()=>[t(o,{title:"总测试次数",value:n.total_test,"value-style":{color:"#1890ff"}},{prefix:e(()=>[t(d($))]),_:1},8,["value"])]),_:1})]),_:1}),t(r,{span:6},{default:e(()=>[t(a,{class:"stat-card"},{default:e(()=>[t(o,{title:"今日测试",value:n.today_test,"value-style":{color:"#52c41a"}},{prefix:e(()=>[t(d(w))]),_:1},8,["value"])]),_:1})]),_:1}),t(r,{span:6},{default:e(()=>[t(a,{class:"stat-card"},{default:e(()=>[t(o,{title:"付费次数",value:n.paid_test,"value-style":{color:"#faad14"}},{prefix:e(()=>[t(d(T))]),_:1},8,["value"])]),_:1})]),_:1}),t(r,{span:6},{default:e(()=>[t(a,{class:"stat-card"},{default:e(()=>[t(o,{title:"总金额",value:n.total_amount,precision:2,"value-style":{color:"#722ed1"}},{prefix:e(()=>[t(d(H))]),_:1},8,["value"])]),_:1})]),_:1})]),_:1}),t(c,{gutter:16},{default:e(()=>[t(r,{span:12},{default:e(()=>[t(a,{title:"五行分布统计",class:"chart-card"},{default:e(()=>[p("div",{ref_key:"elementChartRef",ref:u,class:"chart-container"},null,512)]),_:1})]),_:1}),t(r,{span:12},{default:e(()=>[t(a,{title:"近7天测试趋势",class:"chart-card"},{default:e(()=>[p("div",{ref_key:"trendChartRef",ref:_,class:"chart-container"},null,512)]),_:1})]),_:1})]),_:1})]),_:1})])}}}),z=B(S,[["__scopeId","data-v-9dc36f5d"]]);export{z as default};

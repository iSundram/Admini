import{d as x,o,b as n,e as t,ae as l,h as r,j as a,bD as f,G as p,_ as m}from"../index.DtGSrwJ3.js";const u={class:"whitespace-normal @container/section-page"},_={class:"flex flex-col items-start gap-4"},b=["innerHTML"],h=["innerHTML"],y={class:"flex gap-4 self-stretch"},k={class:"min-w-full"},v=x({__name:"tw-page-section",props:{heading:{default:""},subheading:{default:""},strategy:{default:"breakpoint"},breakpoint:{default:"xl"}},setup(c){const s=c,i=f(()=>({strategy:s.strategy==="breakpoint"?"vertical":s.strategy,breakpoint:s.strategy==="breakpoint"?s.breakpoint:"none"})),g=i({"strategy:horizontal":`
        grid grid-cols-[300px_auto] gap-6
    `,"strategy:vertical":`
        grid grid-cols-1 gap-y-6
    `,"breakpoint:xs":`
        @sm/section-page:grid-cols-[300px_auto] @sm/section-page:gap-x-6
    `,"breakpoint:sm":`
        @xl/section-page:grid-cols-[300px_auto] @xl/section-page:gap-x-6
    `,"breakpoint:md":`
        @4xl/section-page:grid-cols-[300px_auto] @4xl/section-page:gap-x-6
    `,"breakpoint:lg":`
        @7xl/section-page:grid-cols-[300px_auto] @7xl/section-page:gap-x-6
    `,"breakpoint:xl":`
        @[144rem]/section-page:grid-cols-[300px_auto] @[144rem]/section-page:gap-x-6
    `}),d=i({"strategy:horizontal":`
        flex flex-col flex-nowrap items-start justify-start gap-8
    `,"strategy:vertical":`
        flex flex-row flex-nowrap items-center justify-between gap-8
    `,"breakpoint:xs":`
        @sm/section-page:flex-col @sm/section-page:items-start @sm/section-page:justify-start
    `,"breakpoint:sm":`
        @xl/section-page:flex-col @xl/section-page:items-start @xl/section-page:justify-start
    `,"breakpoint:md":`
        @4xl/section-page:flex-col @4xl/section-page:items-start @4xl/section-page:justify-start
    `,"breakpoint:lg":`
        @7xl/section-page:flex-col @7xl/section-page:items-start @7xl/section-page:justify-start
    `,"breakpoint:xl":`
        @[144rem]/section-page:flex-col @[144rem]/section-page:items-start @[144rem]/section-page:justify-start
    `});return(e,w)=>(o(),n("div",u,[t("div",{class:l(r(g))},[t("div",{class:l(r(d))},[t("div",_,[a(e.$slots,"heading",{},()=>[e.heading?(o(),n("h2",{key:0,class:"my-0 font-sans text-lg font-bold leading-normal text-gray-900 dark:text-gray-50",innerHTML:e.heading},null,8,b)):p("",!0)]),a(e.$slots,"subheading",{},()=>[e.subheading?(o(),n("p",{key:0,class:"tw-subheading my-0",innerHTML:e.subheading},null,8,h)):p("",!0)])]),t("div",y,[a(e.$slots,"action")])],2),t("div",k,[a(e.$slots,"default")])],2)]))}}),C=m(v,[["__file","tw-page-section.vue"]]);export{C as _};

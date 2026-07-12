import { useMemo } from 'react'
import * as d3 from 'd3'
import type { Match } from '../types'

export default function Bracket({stages}:{stages:Array<{stage:string;matches:Match[]}>}){
 const width=Math.max(900,stages.length*270),height=Math.max(420,...stages.map(s=>s.matches.length*150+80))
 const columns=useMemo(()=>stages.map((s,ci)=>({stage:s.stage,x:ci*270+20,matches:s.matches.map((m,mi)=>({m,y:(height/(s.matches.length+1))*(mi+1)}))})),[stages,height])
 const paths:string[]=[]
 for(let c=0;c<columns.length-1;c++){const left=columns[c],right=columns[c+1];left.matches.forEach((lm,i)=>{const target=right.matches[Math.min(Math.floor(i/2),right.matches.length-1)];if(!target)return;const x1=left.x+210,x2=right.x,y1=lm.y,y2=target.y;paths.push(`M${x1},${y1} C${d3.mean([x1,x2])},${y1} ${d3.mean([x1,x2])},${y2} ${x2},${y2}`)})}
 return <div className="overflow-x-auto rounded-3xl border border-white/10 bg-black/20 p-3"><svg width={width} height={height} className="min-w-full"><defs><linearGradient id="line" x1="0" x2="1"><stop offset="0" stopColor="#3fffb5" stopOpacity=".55"/><stop offset="1" stopColor="#52c7ff" stopOpacity=".25"/></linearGradient></defs>{paths.map((d,i)=><path key={i} d={d} fill="none" stroke="url(#line)" strokeWidth="2"/>)}{columns.map(col=><g key={col.stage}><text x={col.x} y={30} fill="#94a3b8" fontSize="12" fontWeight="700" letterSpacing="2">{col.stage}</text>{col.matches.map(({m,y})=><g key={m.id} transform={`translate(${col.x},${y-48})`}><rect width="210" height="96" rx="16" fill="#0c1b19" stroke="rgba(255,255,255,.12)"/><text x="16" y="29" fill="#f8fafc" fontSize="13">{m.home_flag} {m.home_team}</text><text x="184" y="29" textAnchor="end" fill="#3fffb5" fontSize="14" fontWeight="800">{m.home_score??'-'}</text><line x1="14" x2="196" y1="47" y2="47" stroke="rgba(255,255,255,.08)"/><text x="16" y="72" fill="#f8fafc" fontSize="13">{m.away_flag} {m.away_team}</text><text x="184" y="72" textAnchor="end" fill="#52c7ff" fontSize="14" fontWeight="800">{m.away_score??'-'}</text></g>)}</g>)}</svg></div>
}

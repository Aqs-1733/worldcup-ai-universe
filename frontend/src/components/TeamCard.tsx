import { Link } from 'react-router-dom'
import { motion } from 'framer-motion'
import type { Team } from '../types'
export default function TeamCard({team,index=0}:{team:Team;index?:number}){return <motion.div initial={{opacity:0,y:16}} animate={{opacity:1,y:0}} transition={{delay:Math.min(index*.035,.35)}}><Link to={`/teams/${team.slug}`} className="panel group block overflow-hidden p-5 transition hover:-translate-y-1 hover:border-neon/30">
 <div className="mb-5 flex items-center justify-between"><div className="grid h-16 w-16 place-items-center rounded-2xl text-5xl" style={{background:`linear-gradient(145deg,${team.primary_color}55,${team.secondary_color}28)`}}>{team.flag}</div><div className="text-right"><div className="text-xs text-slate-500">FIFA RANK</div><div className="text-2xl font-black">#{team.world_rank}</div></div></div>
 <h3 className="text-xl font-black">{team.flag} {team.name}</h3><p className="text-xs uppercase tracking-wider text-slate-500">{team.english_name} · {team.confederation}</p>
 <p className="mt-4 line-clamp-2 text-sm leading-6 text-slate-400">{team.style}</p>
 <div className="mt-5 flex items-end justify-between"><div><div className="text-[10px] text-slate-500">夺冠概率 · 展示模型</div><div className="text-xl font-black text-neon">{team.win_probability}%</div></div><div className="flex gap-1">{team.form.map((x,i)=><span key={i} className={`grid h-6 w-6 place-items-center rounded-lg text-[10px] font-bold ${x==='W'?'bg-neon/15 text-neon':x==='D'?'bg-amber-300/15 text-amber-200':'bg-red-400/15 text-red-300'}`}>{x}</span>)}</div></div>
 </Link></motion.div>}

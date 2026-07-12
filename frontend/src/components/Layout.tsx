import { useEffect, useState } from 'react'
import { NavLink, Outlet, useLocation } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { AnimatePresence, motion } from 'framer-motion'
import { BrainCircuit, CalendarDays, ChevronRight, CircleUserRound, Eye, Home, Menu, Moon, Newspaper, ShieldCheck, Sparkles, Sun, Users, X } from 'lucide-react'
import { getHealth, getUsers } from '../lib/api'
import { activeUserFrom } from '../lib/session'
import LoginGate from './LoginGate'
import OnboardingSurvey from './OnboardingSurvey'

const nav = [
  {to:'/',label:'首页',icon:Home,end:true},
  {to:'/fan-space',label:'球迷空间',icon:CircleUserRound},
  {to:'/worldcup',label:'世界杯中心',icon:CalendarDays},
  {to:'/teams',label:'球队中心',icon:ShieldCheck},
  {to:'/players',label:'球员中心',icon:Users},
  {to:'/news',label:'新闻中心',icon:Newspaper},
  {to:'/ai-studio',label:'AI 分析创作',icon:BrainCircuit},
  {to:'/vision',label:'视觉分析',icon:Eye},
]

function Sidebar({onClose}:{onClose?:()=>void}){
  return <aside className="flex h-full w-[270px] flex-col border-r border-white/10 bg-[#07110f]/95 px-4 py-5 backdrop-blur-2xl">
    <div className="mb-8 flex items-center gap-3 px-2">
      <div className="relative grid h-12 w-12 place-items-center rounded-2xl bg-neon text-2xl text-pitch shadow-[0_0_35px_rgba(63,255,181,.25)]">⚽
        <span className="absolute -right-1 -top-1 h-3 w-3 animate-pulse rounded-full bg-cyanai ring-4 ring-[#07110f]" />
      </div>
      <div><div className="text-[10px] font-black tracking-[.22em] text-neon">WORLDCUP AI</div><div className="font-black tracking-tight">Universe</div></div>
    </div>
    <nav className="space-y-1.5">
      {nav.map(({to,label,icon:Icon,end})=><NavLink key={to} to={to} end={end} onClick={onClose} className={({isActive})=>`group flex items-center gap-3 rounded-2xl px-3 py-3 text-sm font-semibold transition ${isActive?'bg-neon text-pitch shadow-[0_10px_30px_rgba(63,255,181,.16)]':'text-slate-400 hover:bg-white/[.06] hover:text-white'}`}>
        <Icon size={18}/><span className="flex-1">{label}</span><ChevronRight size={15} className="opacity-0 transition group-hover:opacity-70"/>
      </NavLink>)}
    </nav>
    <div className="mt-auto rounded-3xl border border-neon/20 bg-neon/[.06] p-4">
      <div className="mb-2 flex items-center gap-2 text-xs font-black text-neon"><Sparkles size={15}/> AI ENGINE</div>
      <p className="text-xs leading-5 text-slate-400">LangGraph 多Agent · Chroma RAG · ARK 增强</p>
      <div className="mt-3 flex items-center gap-2 text-[11px] text-slate-500"><span className="h-2 w-2 animate-pulse rounded-full bg-neon"/>系统在线</div>
    </div>
  </aside>
}

export default function Layout(){
  const [open,setOpen]=useState(false)
  const [theme,setTheme]=useState(()=>localStorage.getItem('wc-theme') || 'dark')
  const location=useLocation()
  const {data:health}=useQuery({queryKey:['health'],queryFn:getHealth})
  const users=useQuery({queryKey:['users'],queryFn:getUsers})
  const activeUser=activeUserFrom(users.data)
  const current=nav.find(x=>x.to==='/'?location.pathname==='/':location.pathname.startsWith(x.to))
  useEffect(()=>{document.documentElement.dataset.theme=theme;localStorage.setItem('wc-theme',theme)},[theme])
  return <div className="min-h-screen lg:flex">
    <div className="fixed inset-y-0 left-0 z-40 hidden lg:block"><Sidebar/></div>
    <AnimatePresence>{open&&<><motion.div initial={{opacity:0}} animate={{opacity:1}} exit={{opacity:0}} className="fixed inset-0 z-40 bg-black/70 lg:hidden" onClick={()=>setOpen(false)}/><motion.div initial={{x:-300}} animate={{x:0}} exit={{x:-300}} className="fixed inset-y-0 left-0 z-50 lg:hidden"><button className="absolute right-3 top-3 z-10 rounded-xl bg-white/10 p-2" onClick={()=>setOpen(false)}><X size={18}/></button><Sidebar onClose={()=>setOpen(false)}/></motion.div></>}</AnimatePresence>
    <main className="min-w-0 flex-1 lg:pl-[270px]">
      <header className="sticky top-0 z-30 flex h-16 items-center border-b border-white/10 bg-[#06100f]/80 px-4 backdrop-blur-xl md:px-8">
        <button className="mr-3 rounded-xl border border-white/10 p-2 lg:hidden" onClick={()=>setOpen(true)}><Menu size={19}/></button>
        <div><div className="text-xs text-slate-500">世界杯AI数字球迷生态系统</div><div className="font-black">{current?.label??'WorldCup AI Universe'}</div></div>
        <div className="ml-auto flex items-center gap-3">
          <div className="hidden items-center gap-2 rounded-full border border-white/10 bg-white/[.04] px-3 py-1.5 text-xs text-slate-400 sm:flex"><span className={`h-2 w-2 rounded-full ${health?'bg-neon':'bg-amber-400'}`}/>{health?`${health.llm_mode==='ark'?'ARK增强':'本地模式'}`:'连接中'}</div>
          <button className="rounded-xl border border-white/10 bg-white/[.04] p-2 text-slate-300" onClick={()=>setTheme(theme==='dark'?'light':'dark')} title="切换深浅色">{theme==='dark'?<Sun size={16}/>:<Moon size={16}/>}</button>
          <NavLink to="/ai-studio" className="rounded-xl bg-neon px-3 py-2 text-xs font-black text-pitch">问 AI</NavLink>
        </div>
      </header>
      <div className="mx-auto max-w-[1600px] px-4 py-6 md:px-8 md:py-8"><Outlet/></div>
    </main>
    {!users.isLoading && !activeUser && <LoginGate/>}
    {activeUser && <OnboardingSurvey/>}
  </div>
}

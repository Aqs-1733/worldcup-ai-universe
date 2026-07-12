import { useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import ReactECharts from 'echarts-for-react'
import { Link } from 'react-router-dom'
import {
  ArrowRight,
  BrainCircuit,
  CalendarDays,
  Camera,
  CircleUserRound,
  Database,
  Newspaper,
  Palette,
  ShieldCheck,
  Sparkles,
  Trophy,
  Users,
} from 'lucide-react'
import Countdown from '../components/Countdown'
import KnockoutPoster from '../components/KnockoutPoster'
import MatchCard from '../components/MatchCard'
import SectionHeader from '../components/SectionHeader'
import StatCard from '../components/StatCard'
import TeamCard from '../components/TeamCard'
import { ErrorBox, Loading } from '../components/Loading'
import { getBracket, getHealth, getMatches, getNews, getOverview, getTeams } from '../lib/api'
import type { Match } from '../types'

const neutralPrompts = [
  '我支持的球队夺冠概率',
  '某支球队的战术特点',
  '某位球员近期状态',
  '判断一条新闻真假',
]

const featureModules = [
  { title: 'AI 分析', desc: '球队 / 球员 / 预测', to: '/ai-studio', icon: BrainCircuit },
  { title: '球迷空间', desc: '画像 / 日报', to: '/fan-space', icon: CircleUserRound },
  { title: '世界杯中心', desc: '赛程 / 积分 / 历史', to: '/worldcup', icon: CalendarDays },
  { title: '新闻核验', desc: '来源 / 可信度', to: '/news', icon: Newspaper },
  { title: '视觉分析', desc: '球衣 / 国旗 / 阵型', to: '/vision', icon: Camera },
  { title: 'AIGC 创作', desc: '海报 / 文案 / 脚本', to: '/ai-studio', icon: Palette },
]

function nextMatches(matches: Match[]) {
  const scheduled = matches.filter((match) => match.status === 'scheduled')
  return (scheduled.length ? scheduled : matches).slice(0, 6)
}

export default function Home() {
  const health = useQuery({ queryKey: ['health'], queryFn: getHealth })
  const teams = useQuery({ queryKey: ['teams'], queryFn: getTeams })
  const matches = useQuery({ queryKey: ['matches'], queryFn: () => getMatches(), refetchInterval: 60_000 })
  const bracket = useQuery({ queryKey: ['bracket'], queryFn: () => getBracket(), refetchInterval: 60_000 })
  const news = useQuery({ queryKey: ['news'], queryFn: () => getNews(), refetchInterval: 30_000 })
  const overview = useQuery({ queryKey: ['overview'], queryFn: getOverview })

  const allMatches = useMemo(() => matches.data ?? [], [matches.data])
  const topTeams = useMemo(
    () => [...(teams.data ?? [])].sort((a, b) => b.win_probability - a.win_probability).slice(0, 4),
    [teams.data],
  )
  const scheduleFocus = useMemo(() => nextMatches(allMatches), [allMatches])
  const statusCounts = useMemo(
    () => ({
      scheduled: allMatches.filter((match) => match.status === 'scheduled').length,
      finished: allMatches.filter((match) => match.status === 'finished').length,
      groups: new Set(allMatches.map((match) => match.group_name).filter(Boolean)).size,
      stages: new Set(allMatches.map((match) => match.stage)).size,
    }),
    [allMatches],
  )

  if (teams.isLoading || matches.isLoading || overview.isLoading || bracket.isLoading) return <Loading label="正在装载世界杯总览" />
  if (teams.isError || matches.isError || overview.isError || bracket.isError) return <ErrorBox />

  const chart = {
    backgroundColor: 'transparent',
    tooltip: { trigger: 'axis' },
    grid: { left: 36, right: 20, top: 25, bottom: 30 },
    xAxis: {
      type: 'category',
      data: topTeams.map((team) => `${team.flag} ${team.name}`),
      axisLine: { lineStyle: { color: 'rgba(255,255,255,.15)' } },
      axisLabel: { color: '#94a3b8' },
    },
    yAxis: {
      type: 'value',
      axisLabel: { color: '#64748b', formatter: '{value}%' },
      splitLine: { lineStyle: { color: 'rgba(255,255,255,.06)' } },
    },
    series: [
      {
        type: 'bar',
        data: topTeams.map((team) => team.win_probability),
        barWidth: 28,
        itemStyle: {
          borderRadius: [10, 10, 0, 0],
          color: {
            type: 'linear',
            x: 0,
            y: 0,
            x2: 0,
            y2: 1,
            colorStops: [
              { offset: 0, color: '#3fffb5' },
              { offset: 1, color: 'rgba(82,199,255,.18)' },
            ],
          },
        },
        label: { show: true, position: 'top', color: '#e2e8f0', formatter: '{c}%' },
      },
    ],
  }

  return (
    <div className="space-y-9">
      <section className="grid gap-6 xl:grid-cols-[.72fr_1.28fr] xl:items-stretch">
        <div className="relative overflow-hidden rounded-[34px] border border-white/10 bg-[#081514] p-6 shadow-glow md:p-8">
          <div className="absolute right-6 top-4 text-[120px] opacity-10 md:text-[170px]">⚽</div>
          <div className="relative flex h-full flex-col">
            <div className="eyebrow mb-4 flex items-center gap-2"><Sparkles size={14} /> WORLD CUP 2026</div>
            <h1 className="bg-gradient-to-r from-neon via-cyanai to-amber-200 bg-clip-text text-4xl font-black leading-tight text-transparent md:text-6xl">
              世界杯总览
            </h1>
            <div className="mt-6 grid grid-cols-2 gap-3">
              <Mini label="待赛" value={statusCounts.scheduled} tone="neon" />
              <Mini label="已完赛" value={statusCounts.finished} tone="cyan" />
              <Mini label="小组" value={statusCounts.groups} />
              <Mini label="阶段" value={statusCounts.stages} />
            </div>
            <div className="mt-6 flex flex-wrap gap-3">
              <Link to="/worldcup" className="btn-primary"><CalendarDays size={18} />赛程</Link>
              <Link to="/ai-studio" className="btn-secondary"><BrainCircuit size={18} />AI</Link>
            </div>
            <div className="mt-5 flex flex-wrap gap-2">
              {neutralPrompts.map((prompt) => (
                <Link key={prompt} to={`/ai-studio?q=${encodeURIComponent(prompt)}`} className="chip">{prompt}</Link>
              ))}
            </div>
            <div className="mt-auto pt-6">
              <div className="panel p-5">
                <div className="mb-4 flex items-center justify-between">
                  <div>
                    <div className="eyebrow">FINAL</div>
                    <div className="mt-1 text-lg font-black">决赛倒计时</div>
                  </div>
                  <Trophy className="text-neon" />
                </div>
                <Countdown target={overview.data?.target_date ?? '2026-07-19T12:00:00-07:00'} />
              </div>
            </div>
          </div>
        </div>
        <KnockoutPoster stages={bracket.data ?? []} teams={teams.data ?? []} />
      </section>

      <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label="球队" value={health.data?.counts?.teams ?? 48} sub="球队档案" icon={ShieldCheck} />
        <StatCard label="球员" value={health.data?.counts?.players ?? 69} sub="球星档案" icon={Users} />
        <StatCard label="赛程" value={allMatches.length} sub="ESPN 实时" icon={CalendarDays} />
        <StatCard label="知识库" value="RAG" sub="历史 / 战术" icon={Database} />
      </section>

      <section>
        <SectionHeader
          eyebrow="SCHEDULE"
          title="赛程"
          action={<Link to="/worldcup" className="chip">完整赛程 <ArrowRight size={13} /></Link>}
        />
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          {scheduleFocus.map((match) => <MatchCard key={match.id} match={match} />)}
        </div>
      </section>

      <section>
        <SectionHeader eyebrow="MODULES" title="功能区" />
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          {featureModules.map(({ title, desc, to, icon: Icon }) => (
            <Link key={title} to={to} className="rounded-3xl border border-white/10 bg-white/[.035] p-5 transition hover:border-neon/30 hover:bg-white/[.06]">
              <div className="mb-5 grid h-12 w-12 place-items-center rounded-2xl bg-neon/10 text-neon"><Icon size={22} /></div>
              <div className="text-lg font-black">{title}</div>
              <div className="mt-2 text-sm text-slate-400">{desc}</div>
            </Link>
          ))}
        </div>
      </section>

      <section className="grid gap-6 xl:grid-cols-[1fr_.9fr]">
        <div>
          <SectionHeader eyebrow="TEAMS" title="热门球队" action={<Link to="/teams" className="chip">全部球队 <ArrowRight size={13} /></Link>} />
          <div className="grid gap-4 sm:grid-cols-2">{topTeams.map((team, index) => <TeamCard key={team.id} team={team} index={index} />)}</div>
        </div>
        <div className="panel p-5 md:p-6">
          <SectionHeader eyebrow="PROBABILITY" title="夺冠概率" />
          <ReactECharts option={chart} style={{ height: 330 }} />
        </div>
      </section>

      <section className="grid gap-6 lg:grid-cols-[1.15fr_.85fr]">
        <div className="panel p-5 md:p-6">
          <SectionHeader eyebrow="NEWS" title="新闻" action={<Link to="/news" className="chip"><Newspaper size={13} />新闻中心</Link>} />
          <div className="space-y-3">
            {(news.data ?? []).slice(0, 4).map((item, index) => (
              <a key={item.id} href={item.source_url || undefined} target="_blank" rel="noreferrer" className="group flex gap-4 rounded-2xl border border-white/5 bg-white/[.025] p-4 transition hover:border-neon/20">
                <div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-white/[.06] font-black text-neon">{String(index + 1).padStart(2, '0')}</div>
                <div className="min-w-0 flex-1">
                  <div className="font-bold leading-6 group-hover:text-neon">{item.title}</div>
                  <div className="mt-2 flex flex-wrap gap-2 text-[11px] text-slate-500">
                    <span>{item.source}</span><span>·</span><span>{item.credibility_score}% {item.credibility_label}</span>
                  </div>
                </div>
              </a>
            ))}
          </div>
        </div>
        <div className="panel p-6">
          <div className="mb-5 grid h-14 w-14 place-items-center rounded-2xl bg-gradient-to-br from-neon to-cyanai text-pitch"><BrainCircuit /></div>
          <div className="eyebrow">AI STUDIO</div>
          <h3 className="mt-2 text-2xl font-black">AI 分析与创作</h3>
          <div className="mt-5 flex flex-wrap gap-2">
            {neutralPrompts.map((prompt) => <Link key={prompt} to={`/ai-studio?q=${encodeURIComponent(prompt)}`} className="chip">{prompt}</Link>)}
          </div>
          <Link to="/ai-studio" className="btn-primary mt-6">进入 AI Studio <ArrowRight size={17} /></Link>
        </div>
      </section>
    </div>
  )
}

function Mini({ label, value, tone }: { label: string; value: number; tone?: 'neon' | 'cyan' }) {
  return (
    <div className="rounded-2xl border border-white/10 bg-white/[.035] p-4">
      <div className={`text-2xl font-black ${tone === 'neon' ? 'text-neon' : tone === 'cyan' ? 'text-cyanai' : ''}`}>{value}</div>
      <div className="mt-1 text-xs text-slate-500">{label}</div>
    </div>
  )
}

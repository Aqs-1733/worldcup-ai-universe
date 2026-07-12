import { useQuery } from '@tanstack/react-query'
import { ArrowLeft, BrainCircuit, CalendarDays, Goal, Medal, Ruler, Shirt } from 'lucide-react'
import { Link, useParams } from 'react-router-dom'
import { ErrorBox, Loading } from '../components/Loading'
import SectionHeader from '../components/SectionHeader'
import { getPlayer } from '../lib/api'

function initials(name: string) {
  return name.split(/[·\s-]/).filter(Boolean).map((item) => item[0]).slice(-2).join('')
}

function valueOrBlank(value: string | number | null | undefined) {
  if (value === null || value === undefined || value === '') return '未公开'
  return value
}

export default function PlayerDetail() {
  const { slug = '' } = useParams()
  const { data: player, isLoading, isError } = useQuery({
    queryKey: ['player', slug],
    queryFn: () => getPlayer(slug),
  })

  if (isLoading) return <Loading />
  if (isError || !player) return <ErrorBox message="球员资料不存在" />

  return (
    <div className="space-y-8">
      <Link to="/players" className="chip"><ArrowLeft size={13} />返回球员中心</Link>

      <section className="relative overflow-hidden rounded-[34px] border border-white/10 bg-gradient-to-br from-cyanai/20 via-[#0b1819] to-neon/10 p-7 md:p-10">
        <div className="absolute -right-8 -top-10 text-[220px] opacity-[.06]">{player.flag}</div>
        <div className="relative flex flex-col gap-7 md:flex-row md:items-center">
          <div className="grid h-32 w-32 place-items-center rounded-[36px] border border-white/15 bg-black/20 text-5xl font-black backdrop-blur">
            {initials(player.name)}
          </div>
          <div className="flex-1">
            <div className="eyebrow">{player.is_legend ? 'WORLD CUP LEGEND' : 'FIFA OFFICIAL SQUAD'} · {player.position}</div>
            <h1 className="mt-2 text-4xl font-black md:text-6xl">{player.flag} {player.name}</h1>
            <p className="mt-3 text-slate-400">{player.club || '俱乐部未公开'} · {player.flag} {player.country}</p>
          </div>
          <Link className="btn-primary" to={`/ai-studio?q=${encodeURIComponent(`${player.name}官方名单资料和近期新闻`)}`}>
            <BrainCircuit size={17} />AI分析
          </Link>
        </div>
      </section>

      <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <Stat icon={Shirt} label="位置" value={player.position} />
        <Stat icon={CalendarDays} label="年龄" value={valueOrBlank(player.age)} />
        <Stat icon={Medal} label="国家队出场" value={player.world_cup_appearances || '未公开'} />
        <Stat icon={Goal} label="国家队进球" value={player.world_cup_goals || '未公开'} />
      </section>

      <section className="grid gap-6 xl:grid-cols-[.8fr_1.2fr]">
        <div className="panel p-6">
          <SectionHeader eyebrow="OFFICIAL PROFILE" title="官方字段" />
          <div className="space-y-3 text-sm">
            <Info label="英文名" value={player.english_name} />
            <Info label="国家/地区" value={`${player.flag} ${player.country}`} />
            <Info label="俱乐部" value={player.club || '未公开'} />
            <Info label="球衣号码" value={valueOrBlank(player.number)} />
            <Info label="身高" value={player.career.match(/身高 ([^；]+)；/)?.[1] || '未公开'} icon={Ruler} />
          </div>
        </div>

        <div className="panel p-6">
          <SectionHeader eyebrow="SOURCE" title="数据来源" />
          <div className="rounded-2xl border border-cyanai/15 bg-cyanai/[.05] p-5 text-base font-bold leading-8">
            {player.career || '暂无来源说明'}
          </div>
          <p className="muted mt-5">
            本页不再展示平台推算评分。当前球员资料来自官方名单同步；未在来源中出现的状态、助攻、技术标签保持空白。
          </p>
        </div>
      </section>
    </div>
  )
}

function Stat({ icon: Icon, label, value }: { icon: typeof Shirt; label: string; value: string | number }) {
  return (
    <div className="panel p-5">
      <Icon className="mb-5 text-cyanai" size={20} />
      <div className="text-3xl font-black">{value}</div>
      <div className="mt-1 text-sm text-slate-400">{label}</div>
    </div>
  )
}

function Info({ label, value, icon: Icon }: { label: string; value: string | number; icon?: typeof Shirt }) {
  return (
    <div className="flex items-center justify-between gap-4 rounded-2xl bg-white/[.035] p-3">
      <span className="flex items-center gap-2 text-slate-500">{Icon && <Icon size={14} />}{label}</span>
      <span className="text-right font-bold">{value}</span>
    </div>
  )
}

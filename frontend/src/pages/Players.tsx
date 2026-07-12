import { useEffect, useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { RefreshCw, Search, Star } from 'lucide-react'
import EmptyState from '../components/EmptyState'
import SectionHeader from '../components/SectionHeader'
import { ErrorBox, Loading } from '../components/Loading'
import { getPlayers, getTeams, refreshOfficialPlayers } from '../lib/api'
import type { Player, Team } from '../types'

export default function Players() {
  const qc = useQueryClient()
  const players = useQuery({ queryKey: ['players-all'], queryFn: () => getPlayers(), refetchInterval: 300_000 })
  const teams = useQuery({ queryKey: ['teams'], queryFn: getTeams })
  const refresh = useMutation({
    mutationFn: refreshOfficialPlayers,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['players-all'] }),
  })
  const [q, setQ] = useState('')
  const [type, setType] = useState<'all' | 'active' | 'legend'>('active')
  const [pos, setPos] = useState('全部')
  const [team, setTeam] = useState('全部')

  const data = useMemo(() => players.data ?? [], [players.data])
  const activeCount = data.filter((item) => !item.is_legend).length
  const positions = ['全部', ...Array.from(new Set(data.map((item) => item.position)))]
  const filtered = useMemo(
    () => data.filter((item) =>
      (type === 'all' || (type === 'legend' ? item.is_legend : !item.is_legend)) &&
      (pos === '全部' || item.position === pos) &&
      (team === '全部' || item.country === team) &&
      (!q || `${item.name}${item.english_name}${item.club}${item.country}`.toLowerCase().includes(q.toLowerCase())),
    ),
    [data, q, type, pos, team],
  )

  const grouped = useMemo(() => {
    const list = teams.data ?? []
    return list
      .map((item) => ({ team: item, players: filtered.filter((player) => player.country === item.name) }))
      .filter((group) => group.players.length)
  }, [filtered, teams.data])

  useEffect(() => {
    if (!players.isLoading && activeCount > 0 && activeCount < 500 && !refresh.isPending && !refresh.data) {
      refresh.mutate()
    }
  }, [activeCount, players.isLoading, refresh])

  if (players.isLoading || teams.isLoading) return <Loading />
  if (players.isError || teams.isError) return <ErrorBox />

  return (
    <div className="space-y-7">
      <section className="rounded-[32px] border border-white/10 bg-gradient-to-br from-[#102333] to-[#09120f] p-6 md:p-9">
        <div className="eyebrow">PLAYERS & SQUADS</div>
        <h1 className="mt-3 bg-gradient-to-r from-neon via-cyanai to-amber-200 bg-clip-text text-3xl font-black text-transparent md:text-5xl">
          球员中心
        </h1>
      </section>

      <div className="panel space-y-3 p-4">
        <div className="flex flex-col gap-3 md:flex-row">
          <div className="relative flex-1">
            <Search className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-500" size={17} />
            <input className="input pl-11" placeholder="搜索球员、俱乐部、国家" value={q} onChange={(event) => setQ(event.target.value)} />
          </div>
          <button className="btn-primary" disabled={refresh.isPending} onClick={() => refresh.mutate()}>
            <RefreshCw size={16} className={refresh.isPending ? 'animate-spin' : ''} />
            {refresh.isPending ? '同步中' : '同步FIFA名单'}
          </button>
        </div>
        {refresh.data && (
          <div className="rounded-2xl border border-neon/15 bg-neon/[.05] p-3 text-sm text-slate-300">
            来源：FIFA 官方 SquadLists-English.pdf；解析 {refresh.data.parsed} 人，新增 {refresh.data.inserted} 人，更新 {refresh.data.updated} 人。
          </div>
        )}
        <div className="flex flex-wrap gap-2">
          <button className={`chip ${type === 'active' ? 'border-neon/50 bg-neon/15 text-neon' : ''}`} onClick={() => setType('active')}>国家队阵容</button>
          <button className={`chip ${type === 'legend' ? 'border-neon/50 bg-neon/15 text-neon' : ''}`} onClick={() => setType('legend')}><Star size={12} />传奇球员</button>
          <button className={`chip ${type === 'all' ? 'border-neon/50 bg-neon/15 text-neon' : ''}`} onClick={() => setType('all')}>全部</button>
          <span className="mx-1 h-7 w-px bg-white/10" />
          {positions.map((item) => <button key={item} className={`chip ${pos === item ? 'border-cyanai/50 bg-cyanai/10 text-cyanai' : ''}`} onClick={() => setPos(item)}>{item}</button>)}
        </div>
        <div className="flex gap-2 overflow-x-auto pb-1">
          <button className={`chip shrink-0 ${team === '全部' ? 'border-neon/50 bg-neon/15 text-neon' : ''}`} onClick={() => setTeam('全部')}>全部球队</button>
          {(teams.data ?? []).map((item) => (
            <button key={item.id} className={`chip shrink-0 ${team === item.name ? 'border-neon/50 bg-neon/15 text-neon' : ''}`} onClick={() => setTeam(item.name)}>
              {item.flag} {item.name}
            </button>
          ))}
        </div>
      </div>

      <SectionHeader title={`${filtered.length} 名球员`} />
      {!filtered.length && <EmptyState title="没有匹配球员" />}

      {team === '全部' && type !== 'legend' ? (
        <div className="space-y-6">
          {grouped.map((group) => <SquadGroup key={group.team.id} team={group.team} players={group.players} />)}
        </div>
      ) : (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
          {filtered.map((item) => <PlayerTile key={item.id} player={item} />)}
        </div>
      )}
    </div>
  )
}

function SquadGroup({ team, players }: { team: Team; players: Player[] }) {
  return (
    <section className="panel p-4 md:p-5">
      <div className="mb-4 flex items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <div className="grid h-12 w-12 place-items-center rounded-2xl border border-white/10 bg-white/[.05] text-3xl">{team.flag}</div>
          <div>
            <h2 className="font-black">{team.flag} {team.name}</h2>
            <div className="text-xs text-slate-500">{team.english_name}</div>
          </div>
        </div>
        <span className="chip">{players.length} 人 · FIFA名单</span>
      </div>
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
        {players.map((player) => <PlayerTile key={player.id} player={player} />)}
      </div>
    </section>
  )
}

function PlayerTile({ player }: { player: Player }) {
  return (
    <Link to={`/players/${player.slug}`} className="rounded-2xl border border-white/10 bg-white/[.035] p-3 transition hover:border-cyanai/35 hover:bg-white/[.06]">
      <div className="mb-3 flex items-center justify-between gap-2">
        <div className="min-w-0">
          <div className="truncate text-sm font-black">{player.flag} {player.name}</div>
          <div className="mt-1 truncate text-[11px] text-slate-500">{player.english_name}</div>
        </div>
        <div className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-cyanai/10 text-xs font-black text-cyanai">
          {player.number ?? '-'}
        </div>
      </div>
      <div className="flex items-center justify-between text-xs text-slate-400">
        <span>{player.position}</span>
        <span>{player.world_cup_appearances ? `${player.world_cup_appearances}场` : ''}</span>
      </div>
      <div className="mt-2 truncate text-[11px] text-slate-500">{player.club}</div>
    </Link>
  )
}

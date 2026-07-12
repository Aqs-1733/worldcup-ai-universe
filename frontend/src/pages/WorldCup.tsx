import { useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import ReactECharts from 'echarts-for-react'
import { CalendarDays, GitBranch, History, ListOrdered, Network } from 'lucide-react'
import KnockoutPoster from '../components/KnockoutPoster'
import MatchCard from '../components/MatchCard'
import MatchMindMap from '../components/MatchMindMap'
import SectionHeader from '../components/SectionHeader'
import { ErrorBox, Loading } from '../components/Loading'
import { getBracket, getHistory, getMatches, getStandings, getTeams } from '../lib/api'
import { labelWithFlag } from '../lib/teamFlags'
import type { Standing } from '../types'

const tabs = [
  ['schedule', '赛程', CalendarDays],
  ['mindmap', '对战图谱', Network],
  ['standings', '积分榜', ListOrdered],
  ['bracket', '晋级图', GitBranch],
  ['history', '历届冠军', History],
] as const

export default function WorldCup() {
  const [tab, setTab] = useState<(typeof tabs)[number][0]>('schedule')
  const matches = useQuery({ queryKey: ['matches'], queryFn: () => getMatches(), refetchInterval: 60_000 })
  const standings = useQuery({ queryKey: ['standings'], queryFn: getStandings, refetchInterval: 60_000 })
  const bracket = useQuery({ queryKey: ['bracket'], queryFn: () => getBracket(), refetchInterval: 60_000 })
  const history = useQuery({ queryKey: ['history'], queryFn: getHistory })
  const teams = useQuery({ queryKey: ['teams'], queryFn: getTeams })

  const groups = useMemo(() => {
    const map: Record<string, Standing[]> = {}
    ;(standings.data ?? []).forEach((item) => (map[item.group_name] ??= []).push(item))
    return map
  }, [standings.data])

  if (matches.isLoading || standings.isLoading || bracket.isLoading || history.isLoading || teams.isLoading) return <Loading />
  if (matches.isError || standings.isError || bracket.isError || history.isError || teams.isError) return <ErrorBox />

  const pointsChart = {
    backgroundColor: 'transparent',
    tooltip: { trigger: 'axis' },
    legend: { textStyle: { color: '#94a3b8' } },
    radar: {
      indicator: (standings.data ?? []).slice(0, 6).map((item) => ({ name: `${item.flag} ${item.team}`, max: 9 })),
      axisName: { color: '#cbd5e1' },
      splitLine: { lineStyle: { color: 'rgba(255,255,255,.08)' } },
      splitArea: { areaStyle: { color: ['rgba(63,255,181,.02)', 'rgba(82,199,255,.02)'] } },
    },
    series: [
      {
        type: 'radar',
        data: [{
          name: '积分',
          value: (standings.data ?? []).slice(0, 6).map((item) => item.points),
          areaStyle: { color: 'rgba(63,255,181,.18)' },
          lineStyle: { color: '#3fffb5' },
          itemStyle: { color: '#3fffb5' },
        }],
      },
    ],
  }

  return (
    <div className="space-y-7">
      <section className="rounded-[32px] border border-white/10 bg-gradient-to-r from-[#12291f] to-[#0a1624] p-6 md:p-9">
        <div className="eyebrow">WORLD CUP DATA</div>
        <h1 className="mt-3 bg-gradient-to-r from-neon via-cyanai to-amber-200 bg-clip-text text-3xl font-black text-transparent md:text-5xl">
          世界杯中心
        </h1>
      </section>

      <div className="flex gap-2 overflow-x-auto pb-1">
        {tabs.map(([id, label, Icon]) => (
          <button
            key={id}
            onClick={() => setTab(id)}
            className={`flex shrink-0 items-center gap-2 rounded-2xl px-4 py-3 text-sm font-bold transition ${tab === id ? 'bg-neon text-pitch' : 'border border-white/10 bg-white/[.04] text-slate-400'}`}
          >
            <Icon size={17} />{label}
          </button>
        ))}
      </div>

      {tab === 'schedule' && (
        <section>
          <SectionHeader title="完整赛程" />
          <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">{matches.data?.map((item) => <MatchCard key={item.id} match={item} />)}</div>
        </section>
      )}

      {tab === 'mindmap' && (
        <section>
          <SectionHeader title="国旗对战图谱" />
          <MatchMindMap matches={matches.data ?? []} />
        </section>
      )}

      {tab === 'standings' && (
        <section className="grid gap-6 xl:grid-cols-[1.2fr_.8fr]">
          <div>
            <SectionHeader title="小组积分榜" />
            <div className="grid gap-5 md:grid-cols-2">
              {Object.entries(groups).map(([group, rows]) => (
                <div className="panel overflow-hidden" key={group}>
                  <div className="border-b border-white/10 px-5 py-4 font-black">{group}</div>
                  <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                      <thead className="text-[10px] uppercase text-slate-600">
                        <tr><th className="px-4 py-3 text-left">球队</th><th>赛</th><th>胜</th><th>平</th><th>负</th><th>净胜</th><th className="pr-4">积分</th></tr>
                      </thead>
                      <tbody>
                        {rows?.map((item, index) => (
                          <tr key={item.id} className="border-t border-white/5">
                            <td className="px-4 py-3 font-bold"><span className="mr-2 text-xs text-slate-600">{index + 1}</span>{item.flag} {item.team}</td>
                            <td className="text-center">{item.played}</td>
                            <td className="text-center">{item.won}</td>
                            <td className="text-center">{item.drawn}</td>
                            <td className="text-center">{item.lost}</td>
                            <td className="text-center">{item.goal_difference > 0 ? '+' : ''}{item.goal_difference}</td>
                            <td className="pr-4 text-center font-black text-neon">{item.points}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>
              ))}
            </div>
          </div>
          <div className="panel p-5">
            <SectionHeader eyebrow="GROUP RADAR" title="积分对比" />
            <ReactECharts option={pointsChart} style={{ height: 420 }} />
          </div>
        </section>
      )}

      {tab === 'bracket' && (
        <section>
          <SectionHeader title="淘汰赛晋级图" />
          <KnockoutPoster stages={bracket.data ?? []} teams={teams.data ?? []} />
        </section>
      )}

      {tab === 'history' && (
        <section>
          <SectionHeader title="历届冠军" />
          <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            {history.data?.map((item: any) => (
              <div key={item.id} className="panel p-5">
                <div className="flex items-center justify-between">
                  <div className="text-3xl font-black text-neon">{item.year}</div>
                  <div className="text-xs text-slate-500">{labelWithFlag(String(item.host), teams.data)}</div>
                </div>
                <div className="mt-5 text-lg font-black">🏆 {labelWithFlag(String(item.champion), teams.data)}</div>
                <div className="mt-1 text-sm text-slate-400">亚军 {labelWithFlag(String(item.runner_up), teams.data)} · {item.score}</div>
                <p className="mt-4 text-sm leading-6 text-slate-500">{item.highlight}</p>
                <div className="mt-3 text-xs text-slate-600">金靴：{item.golden_boot}</div>
              </div>
            ))}
          </div>
        </section>
      )}
    </div>
  )
}

import type { Match } from '../types'

function formatTime(value: string) {
  return new Date(value).toLocaleString('zh-CN', {
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function MatchNode({ match, side }: { match: Match; side: 'left' | 'right' }) {
  const finished = match.status === 'finished'

  return (
    <div className={`relative ${side === 'left' ? 'lg:pr-7' : 'lg:pl-7'}`}>
      <span
        className={`absolute top-1/2 hidden h-px w-7 bg-gradient-to-r from-neon/50 to-cyanai/30 lg:block ${
          side === 'left' ? 'right-0' : 'left-0'
        }`}
      />
      <div className="rounded-2xl border border-white/10 bg-white/[.035] p-4 transition hover:border-neon/30">
        <div className="mb-3 flex items-center justify-between gap-3 text-[10px] font-bold uppercase tracking-wider text-slate-500">
          <span>{match.stage}{match.group_name ? ` · ${match.group_name}` : ''}</span>
          <span>{finished ? '已结束' : formatTime(match.kickoff)}</span>
        </div>
        <div className="grid grid-cols-[1fr_auto_1fr] items-center gap-3">
          <div className="min-w-0 text-right">
            <div className="text-2xl">{match.home_flag}</div>
            <div className="truncate text-sm font-black">{match.home_team}</div>
          </div>
          <div className="rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-center">
            {finished ? (
              <div className="text-sm font-black text-neon">
                {match.home_score} : {match.away_score}
              </div>
            ) : (
              <div className="text-[10px] font-black text-cyanai">VS</div>
            )}
          </div>
          <div className="min-w-0">
            <div className="text-2xl">{match.away_flag}</div>
            <div className="truncate text-sm font-black">{match.away_team}</div>
          </div>
        </div>
        <div className="mt-3 truncate text-[11px] text-slate-500">{match.venue}</div>
      </div>
    </div>
  )
}

export default function MatchMindMap({ matches }: { matches: Match[] }) {
  const shown = matches.slice(0, 10)
  const left = shown.filter((_, index) => index % 2 === 0)
  const right = shown.filter((_, index) => index % 2 === 1)
  const flags = Array.from(
    new Set(shown.flatMap((match) => [match.home_flag, match.away_flag]).filter(Boolean)),
  ).slice(0, 18)

  if (!shown.length) {
    return (
      <div className="rounded-3xl border border-white/10 bg-white/[.035] p-6 text-sm text-slate-400">
        暂无可绘制的对战图谱。
      </div>
    )
  }

  return (
    <div className="rounded-3xl border border-white/10 bg-black/20 p-5 md:p-6">
      <div className="grid gap-5 lg:grid-cols-[1fr_240px_1fr] lg:items-center">
        <div className="space-y-4">{left.map((match) => <MatchNode key={match.id} match={match} side="left" />)}</div>
        <div className="relative order-first grid min-h-64 place-items-center lg:order-none">
          <div className="absolute inset-y-7 left-1/2 hidden w-px bg-gradient-to-b from-transparent via-neon/35 to-transparent lg:block" />
          <div className="relative z-10 rounded-[32px] border border-neon/25 bg-[#081815] p-5 text-center shadow-[0_0_45px_rgba(63,255,181,.12)]">
            <div className="eyebrow">MATCH MIND MAP</div>
            <div className="mt-2 text-2xl font-black">对战图谱</div>
            <div className="mt-5 flex flex-wrap justify-center gap-1.5 text-xl">{flags.map((flag) => <span key={flag}>{flag}</span>)}</div>
          </div>
        </div>
        <div className="space-y-4">{right.map((match) => <MatchNode key={match.id} match={match} side="right" />)}</div>
      </div>
    </div>
  )
}

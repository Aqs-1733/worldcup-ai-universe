import { flagImageForTeam } from '../lib/teamFlags'
import type { Match, Team } from '../types'

type BracketStage = { stage: string; matches: Match[] }
type TeamSide = 'home' | 'away'
type BracketSide = 'left' | 'right'
type Node = { id: string; x: number; y: number; match: Match; side: BracketSide }

const VIEW_W = 1600
const VIEW_H = 760
const BOX_W = 150
const BOX_H = 48
const SCORE_W = 24

function stageMatches(stages: BracketStage[], names: string[]) {
  return stages.find((stage) => names.some((name) => stage.stage.includes(name)))?.matches ?? []
}

function placeholderMatch(id: number, home = '待定', away = '待定'): Match {
  return {
    id,
    stage: 'placeholder',
    group_name: '',
    home_team: home,
    away_team: away,
    home_flag: '',
    away_flag: '',
    kickoff: '',
    venue: '',
    status: 'scheduled',
    home_score: null,
    away_score: null,
    home_penalties: null,
    away_penalties: null,
    minute: null,
    stats: {},
  }
}

function isSymbolicName(name: string) {
  return !name || name === '待定' || name.includes('胜者') || name.includes('负者')
}

function winnerSide(match: Match): TeamSide | null {
  if (match.home_score == null || match.away_score == null) return null
  if (match.home_score > match.away_score) return 'home'
  if (match.away_score > match.home_score) return 'away'
  if (match.home_penalties == null || match.away_penalties == null) return null
  return match.home_penalties > match.away_penalties ? 'home' : 'away'
}

function matchWinner(match?: Match): { name: string; flag: string } | null {
  if (!match) return null
  const side = winnerSide(match)
  if (!side) return null
  return side === 'home'
    ? { name: match.home_team, flag: match.home_flag }
    : { name: match.away_team, flag: match.away_flag }
}

function normalizeName(name: string) {
  return name.replace(/\s+/g, '').toLowerCase()
}

function matchKey(match: Match) {
  return `${match.id}-${match.home_team}-${match.away_team}`
}

function winningTeamName(match?: Match) {
  return matchWinner(match)?.name ?? ''
}

function findAdvancingMatch(matches: Match[], teamName: string, used: Set<string>) {
  if (isSymbolicName(teamName)) return undefined
  const target = normalizeName(teamName)
  return matches.find((match) => {
    if (used.has(matchKey(match))) return false
    return normalizeName(winningTeamName(match)) === target
  })
}

function score(match: Match, side: TeamSide) {
  const base = side === 'home' ? match.home_score : match.away_score
  const pens = side === 'home' ? match.home_penalties : match.away_penalties
  if (base == null) return ''
  return `${base}${pens == null ? '' : `(${pens})`}`
}

function displayName(name: string) {
  if (isSymbolicName(name)) return '待定'
  return name.length > 5 ? name.slice(0, 5) : name
}

function withResolvedTeams(match: Match, home?: { name: string; flag: string } | null, away?: { name: string; flag: string } | null): Match {
  const next = { ...match }
  if (isSymbolicName(next.home_team) && home) {
    next.home_team = home.name
    next.home_flag = home.flag
  }
  if (isSymbolicName(next.away_team) && away) {
    next.away_team = away.name
    next.away_flag = away.flag
  }
  return next
}

function team(match: Match, side: TeamSide, teams?: Team[]) {
  const raw = side === 'home'
    ? { flag: match.home_flag, name: match.home_team, value: score(match, 'home') }
    : { flag: match.away_flag, name: match.away_team, value: score(match, 'away') }
  const symbolic = isSymbolicName(raw.name) || raw.flag.includes('🏳')
  return {
    ...raw,
    flag: symbolic ? '' : raw.flag,
    flagUrl: symbolic ? '' : flagImageForTeam(raw.name, teams),
    name: symbolic ? '待定' : raw.name,
    value: symbolic ? '' : raw.value,
  }
}

function MatchBox({ node, teams }: { node: Node; teams?: Team[] }) {
  const winner = winnerSide(node.match)
  const rows: TeamSide[] = ['home', 'away']
  const scheduled = node.match.status !== 'finished'

  return (
    <g transform={`translate(${node.x},${node.y})`}>
      <rect
        width={BOX_W}
        height={BOX_H}
        rx="8"
        fill={scheduled ? 'rgba(6, 18, 20, .64)' : 'rgba(7, 28, 24, .9)'}
        stroke={scheduled ? 'rgba(82,199,255,.44)' : 'rgba(63,255,181,.42)'}
        strokeDasharray={scheduled ? '5 5' : '0'}
      />
      {rows.map((side, index) => {
        const entry = team(node.match, side, teams)
        const active = !scheduled && winner === side
        const y = index * 24
        return (
          <g key={side} transform={`translate(0,${y})`}>
            <rect
              x="0"
              y="0"
              width={BOX_W}
              height="24"
              rx="5"
              fill={active ? 'rgba(63, 255, 181, .92)' : scheduled ? 'rgba(255,255,255,.055)' : 'rgba(255,255,255,.12)'}
            />
            {entry.flagUrl && <image href={entry.flagUrl} x="7" y="5" width="20" height="14" preserveAspectRatio="xMidYMid slice" />}
            <text x={entry.flagUrl ? 33 : 12} y="16" fill={active ? '#062016' : scheduled ? '#b8cbd2' : '#f8fafc'} fontSize="12" fontWeight="800">
              {displayName(entry.name)}
            </text>
            <rect x={BOX_W - SCORE_W} width={SCORE_W} height="24" fill={active ? 'rgba(6,32,22,.95)' : scheduled ? 'rgba(82,199,255,.12)' : 'rgba(255,255,255,.9)'} />
            <text x={BOX_W - 12} y="17" fill={active ? '#3fffb5' : scheduled ? '#52c7ff' : '#0b1720'} fontSize="13" fontWeight="900" textAnchor="middle">
              {entry.value}
            </text>
          </g>
        )
      })}
    </g>
  )
}

function connector(from: Node, to: Node) {
  const y1 = from.y + BOX_H / 2
  const y2 = to.y + BOX_H / 2
  const startX = from.side === 'left' ? from.x + BOX_W : from.x
  const endX = from.side === 'left' ? to.x : to.x + BOX_W
  const gap = Math.max(30, Math.abs(endX - startX) / 2)
  const mid = from.side === 'left' ? startX + gap : startX - gap
  return `M${startX},${y1} H${mid} V${y2} H${endX}`
}

function finalConnector(from: Node, finalNode: Node) {
  const y1 = from.y + BOX_H / 2
  const y2 = finalNode.y + BOX_H / 2
  if (from.side === 'left') {
    const startX = from.x + BOX_W
    const endX = finalNode.x
    const mid = startX + Math.max(24, (endX - startX) / 2)
    return `M${startX},${y1} H${mid} V${y2} H${endX}`
  }
  const startX = from.x
  const endX = finalNode.x + BOX_W
  const mid = startX - Math.max(24, (startX - endX) / 2)
  return `M${startX},${y1} H${mid} V${y2} H${endX}`
}

function buildNodes(matches: Match[], x: number, yValues: number[], side: BracketSide, offset = 0) {
  return yValues.map((y, index) => ({
    id: `${side}-${x}-${index}-${matches[offset + index]?.id ?? `empty-${index}`}`,
    x,
    y,
    side,
    match: matches[offset + index] ?? placeholderMatch(-1000 - x - index),
  }))
}

function orderRoundForNext(current: Match[], next: Match[], expected: number, idBase: number) {
  if (!next.length) {
    return [
      ...current.slice(0, expected),
      ...Array.from({ length: Math.max(0, expected - current.length) }, (_, index) => placeholderMatch(idBase - index)),
    ].slice(0, expected)
  }
  const used = new Set<string>()
  const ordered: Match[] = []
  next.forEach((match) => {
    ;(['home', 'away'] as TeamSide[]).forEach((side) => {
      const name = side === 'home' ? match.home_team : match.away_team
      const child = findAdvancingMatch(current, name, used)
      if (child) {
        used.add(matchKey(child))
        ordered.push(child)
      }
    })
  })
  current.forEach((match) => {
    if (!used.has(matchKey(match))) ordered.push(match)
  })
  while (ordered.length < expected) ordered.push(placeholderMatch(idBase - ordered.length))
  return ordered.slice(0, expected)
}

function resolvedRound(matches: Match[], previous: Match[], offset: number, count: number, idBase: number) {
  return Array.from({ length: count }, (_, index) => {
    const current = matches[offset + index] ?? placeholderMatch(idBase - index)
    const home = matchWinner(previous[(offset + index) * 2])
    const away = matchWinner(previous[(offset + index) * 2 + 1])
    return withResolvedTeams(current, home, away)
  })
}

export default function KnockoutPoster({ stages, teams }: { stages: BracketStage[]; teams?: Team[] }) {
  const r32 = stageMatches(stages, ['三十二', '32'])
  const rawR16 = stageMatches(stages, ['十六', '16'])
  const rawQf = stageMatches(stages, ['四分之一', 'QF'])
  const rawSf = stageMatches(stages, ['半决赛', 'SF'])
  const rawFinal = stages.find((stage) => stage.stage === '决赛' || stage.stage.toUpperCase() === 'FINAL')?.matches[0]

  if (!r32.length && !rawR16.length && !rawQf.length) {
    return <div className="panel grid min-h-[360px] place-items-center p-6 text-slate-400">暂无晋级图</div>
  }

  const sfBase = orderRoundForNext(rawSf, rawFinal ? [rawFinal] : [], 2, -2200)
  const qfBase = orderRoundForNext(rawQf, sfBase, 4, -2100)
  const r16Base = orderRoundForNext(rawR16, qfBase, 8, -2000)
  const r32Base = r32.length ? orderRoundForNext(r32, r16Base, 16, -1900) : []
  const r16 = resolvedRound(r16Base, r32Base, 0, 8, -2000)
  const qf = resolvedRound(qfBase, r16, 0, 4, -2100)
  const sf = resolvedRound(sfBase, qf, 0, 2, -2200)
  const final = withResolvedTeams(rawFinal ?? placeholderMatch(-2300, '半决赛胜者1', '半决赛胜者2'), matchWinner(sf[0]), matchWinner(sf[1]))
  const finalNode: Node = { id: `final-${final.id}`, x: 725, y: r32.length ? 352 : 276, side: 'left', match: final }
  let labels: Array<[string, number]>
  let nodes: Node[]
  let lines: string[]

  if (r32.length) {
    const y32 = [114, 182, 250, 318, 386, 454, 522, 590]
    const y16 = [148, 284, 420, 556]
    const yQf = [216, 488]
    const ySf = [352]
    const left32 = buildNodes(r32Base, 24, y32, 'left', 0)
    const left16 = buildNodes(r16, 218, y16, 'left', 0)
    const leftQf = buildNodes(qf, 412, yQf, 'left', 0)
    const leftSf = buildNodes(sf, 606, ySf, 'left', 0)
    const rightSf = buildNodes(sf, 844, ySf, 'right', 1)
    const rightQf = buildNodes(qf, 1038, yQf, 'right', 2)
    const right16 = buildNodes(r16, 1232, y16, 'right', 4)
    const right32 = buildNodes(r32Base, 1426, y32, 'right', 8)
    labels = [
      ['32强', 99],
      ['16强', 293],
      ['QF', 487],
      ['四强', 681],
      ['FINAL', 800],
      ['四强', 919],
      ['QF', 1113],
      ['16强', 1307],
      ['32强', 1501],
    ]
    lines = [
      ...left32.map((node, index) => connector(node, left16[Math.floor(index / 2)])),
      ...left16.map((node, index) => connector(node, leftQf[Math.floor(index / 2)])),
      ...leftQf.map((node) => connector(node, leftSf[0])),
      finalConnector(leftSf[0], finalNode),
      finalConnector(rightSf[0], finalNode),
      ...rightQf.map((node) => connector(node, rightSf[0])),
      ...right16.map((node, index) => connector(node, rightQf[Math.floor(index / 2)])),
      ...right32.map((node, index) => connector(node, right16[Math.floor(index / 2)])),
    ]
    nodes = [...left32, ...left16, ...leftQf, ...leftSf, finalNode, ...rightSf, ...rightQf, ...right16, ...right32]
  } else {
    const y16 = [114, 222, 330, 438]
    const yQf = [168, 384]
    const ySf = [276]
    const left16 = buildNodes(r16, 44, y16, 'left', 0)
    const leftQf = buildNodes(qf, 282, yQf, 'left', 0)
    const leftSf = buildNodes(sf, 520, ySf, 'left', 0)
    const rightSf = buildNodes(sf, 930, ySf, 'right', 1)
    const rightQf = buildNodes(qf, 1168, yQf, 'right', 2)
    const right16 = buildNodes(r16, 1406, y16, 'right', 4)
    labels = [
      ['16强', 119],
      ['QF', 357],
      ['四强', 595],
      ['FINAL', 800],
      ['四强', 1005],
      ['QF', 1243],
      ['16强', 1481],
    ]
    lines = [
      ...left16.map((node, index) => connector(node, leftQf[Math.floor(index / 2)])),
      ...leftQf.map((node) => connector(node, leftSf[0])),
      finalConnector(leftSf[0], finalNode),
      finalConnector(rightSf[0], finalNode),
      ...rightQf.map((node) => connector(node, rightSf[0])),
      ...right16.map((node, index) => connector(node, rightQf[Math.floor(index / 2)])),
    ]
    nodes = [...left16, ...leftQf, ...leftSf, finalNode, ...rightSf, ...rightQf, ...right16]
  }

  return (
    <div className="relative overflow-hidden rounded-[30px] border border-white/10 bg-[#061412] shadow-[0_24px_70px_rgba(0,0,0,.35)]">
      <div className="absolute inset-0 bg-[radial-gradient(circle_at_50%_42%,rgba(63,255,181,.18),transparent_32%),radial-gradient(circle_at_12%_12%,rgba(82,199,255,.16),transparent_24%),linear-gradient(135deg,rgba(8,38,33,.92),rgba(3,10,14,.98)_58%,rgba(8,24,38,.95))]" />
      <div className="absolute inset-0 opacity-[.16] [background-image:linear-gradient(90deg,rgba(255,255,255,.16)_1px,transparent_1px),linear-gradient(rgba(255,255,255,.13)_1px,transparent_1px)] [background-size:42px_42px]" />
      <div className="relative overflow-x-auto p-3">
        <svg viewBox={`0 0 ${VIEW_W} ${VIEW_H}`} className="w-full min-w-[1000px]" role="img" aria-label="世界杯淘汰赛晋级图">
          <defs>
            <filter id="posterShadow" x="-20%" y="-20%" width="140%" height="140%">
              <feDropShadow dx="0" dy="5" stdDeviation="5" floodColor="#03100c" floodOpacity=".55" />
            </filter>
            <linearGradient id="posterLine" x1="0" x2="1">
              <stop offset="0" stopColor="#3fffb5" />
              <stop offset="1" stopColor="#52c7ff" />
            </linearGradient>
          </defs>

          <text x={VIEW_W / 2} y="56" textAnchor="middle" fill="#eafff8" fontSize="44" fontWeight="950" letterSpacing="1">
            WORLD CUP 2026
          </text>
          {labels.map(([label, x]) => (
            <text key={`${label}-${x}`} x={Number(x)} y="96" textAnchor="middle" fill={label === 'FINAL' ? '#3fffb5' : '#52c7ff'} fontSize="15" fontWeight="900">
              {label}
            </text>
          ))}

          <g stroke="url(#posterLine)" strokeWidth="3" fill="none" filter="url(#posterShadow)">
            {lines.map((line, index) => <path key={index} d={line} />)}
          </g>

          <g filter="url(#posterShadow)">
            {nodes.map((node) => <MatchBox key={node.id} node={node} teams={teams} />)}
          </g>
        </svg>
      </div>
    </div>
  )
}

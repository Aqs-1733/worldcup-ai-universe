import { motion } from 'framer-motion'
import { Link } from 'react-router-dom'
import type { Player } from '../types'

const initials = (name: string) => name.split(/[·\s-]/).filter(Boolean).map((item) => item[0]).slice(-2).join('')

export default function PlayerCard({ player, index = 0 }: { player: Player; index?: number }) {
  return (
    <motion.div initial={{ opacity: 0, y: 14 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: Math.min(index * .03, .3) }}>
      <Link to={`/players/${player.slug}`} className="panel group block overflow-hidden transition hover:-translate-y-1 hover:border-cyanai/30">
        <div className="relative h-40 overflow-hidden bg-gradient-to-br from-cyanai/15 via-neon/5 to-transparent">
          <div className="absolute -right-5 -top-6 text-[120px] opacity-10">{player.flag}</div>
          <div className="absolute bottom-4 left-5 grid h-20 w-20 place-items-center rounded-3xl border border-white/10 bg-black/30 text-2xl font-black backdrop-blur">
            {initials(player.name)}
          </div>
          <div className="absolute right-4 top-4 rounded-full bg-black/30 px-3 py-1 text-xs font-bold">
            {player.is_legend ? '传奇' : 'FIFA名单'}
          </div>
        </div>
        <div className="p-5">
          <h3 className="text-lg font-black">{player.flag} {player.name}</h3>
          <p className="mt-1 truncate text-xs text-slate-500">{player.position} · {player.club || '俱乐部未公开'}</p>
          <div className="mt-4 grid grid-cols-2 gap-2 text-center text-xs">
            <div className="rounded-xl bg-white/[.035] p-2">
              <div className="font-black text-cyanai">{player.world_cup_appearances || '-'}</div>
              <div className="mt-1 text-[10px] text-slate-500">国家队出场</div>
            </div>
            <div className="rounded-xl bg-white/[.035] p-2">
              <div className="font-black text-neon">{player.world_cup_goals || '-'}</div>
              <div className="mt-1 text-[10px] text-slate-500">国家队进球</div>
            </div>
          </div>
        </div>
      </Link>
    </motion.div>
  )
}

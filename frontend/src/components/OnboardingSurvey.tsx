import { useEffect, useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { getPlayers, getTeams, getUsers, saveUser } from '../lib/api'
import { activeUserFrom } from '../lib/session'

type Choice = { value: string; label: string }

const contentTypes = ['比赛内容', '战术分析', '球星动态', '娱乐内容', '二创内容', '短视频']

export default function OnboardingSurvey() {
  const qc = useQueryClient()
  const teams = useQuery({ queryKey: ['teams'], queryFn: getTeams })
  const players = useQuery({ queryKey: ['players'], queryFn: () => getPlayers({ legends: false }) })
  const users = useQuery({ queryKey: ['users'], queryFn: getUsers })
  const active = activeUserFrom(users.data)
  const [visible, setVisible] = useState(false)
  const [favoriteTeams, setFavoriteTeams] = useState<string[]>([])
  const [favoritePlayers, setFavoritePlayers] = useState<string[]>([])
  const [blockedTeams, setBlockedTeams] = useState<string[]>([])
  const [contentType, setContentType] = useState('比赛内容')

  const teamChoices = useMemo<Choice[]>(() => (teams.data ?? []).map((team) => ({ value: team.name, label: `${team.flag} ${team.name}` })), [teams.data])
  const playerChoices = useMemo<Choice[]>(() => (players.data ?? []).map((player) => ({
    value: player.name,
    label: `${player.flag} ${player.name}${player.english_name !== player.name ? ` / ${player.english_name}` : ''}`,
  })), [players.data])
  const save = useMutation({
    mutationFn: () => saveUser({
      username: active?.username ?? '我的球迷档案',
      favorite_teams: favoriteTeams,
      favorite_players: favoritePlayers,
      dislike_teams: blockedTeams,
      favorite_content_type: contentType,
      onboarding_completed: true,
    }),
    onSuccess: async (user) => {
      setVisible(false)
      await qc.invalidateQueries({ queryKey: ['users'] })
      await qc.invalidateQueries({ queryKey: ['daily', user.id] })
    },
  })
  const skip = useMutation({
    mutationFn: () => saveUser({
      username: active?.username ?? '我的球迷档案',
      favorite_teams: active?.favorite_teams ?? [],
      favorite_players: active?.favorite_players ?? [],
      dislike_teams: active?.dislike_teams ?? [],
      favorite_content_type: active?.favorite_content_type || '综合',
      onboarding_completed: true,
    }),
    onSuccess: async (user) => {
      setVisible(false)
      await qc.invalidateQueries({ queryKey: ['users'] })
      await qc.invalidateQueries({ queryKey: ['daily', user.id] })
    },
  })

  useEffect(() => {
    if (!active) return
    setVisible(!active.onboarding_completed)
  }, [active])

  if (!visible || teams.isLoading || players.isLoading) return null

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto bg-black/70 p-4 backdrop-blur-sm">
      <section className="mx-auto my-6 max-h-[calc(100vh-3rem)] w-full max-w-4xl overflow-y-auto rounded-[30px] border border-white/10 bg-[#07110f] p-5 shadow-[0_30px_90px_rgba(0,0,0,.45)] md:p-7">
        <div className="mb-5 flex items-start justify-between gap-4">
          <div>
            <div className="eyebrow">FIRST SETUP</div>
            <h2 className="mt-2 text-2xl font-black">选择你的世界杯偏好</h2>
          </div>
        </div>
        <div className="grid gap-5 lg:grid-cols-2">
          <ChoiceBox title="主队和支持球队" items={teamChoices} selected={favoriteTeams} onChange={setFavoriteTeams} />
          <ChoiceBox title="支持球员" items={playerChoices} selected={favoritePlayers} onChange={setFavoritePlayers} searchable />
          <ChoiceBox title="屏蔽球队消息" items={teamChoices} selected={blockedTeams} onChange={setBlockedTeams} />
          <div>
            <div className="mb-3 text-xs font-bold text-slate-400">推送偏好</div>
            <div className="flex flex-wrap gap-2">
              {contentTypes.map((item) => (
                <button key={item} className={`chip ${contentType === item ? 'border-neon/50 bg-neon/15 text-neon' : ''}`} onClick={() => setContentType(item)}>
                  {item}
                </button>
              ))}
            </div>
          </div>
        </div>
        <div className="mt-6 flex flex-wrap justify-end gap-3">
          <button className="btn-secondary" onClick={() => skip.mutate()} disabled={save.isPending || skip.isPending}>
            {skip.isPending ? '处理中' : '跳过'}
          </button>
          <button className="btn-primary" onClick={() => save.mutate()} disabled={save.isPending || skip.isPending}>{save.isPending ? '保存中' : '保存并进入'}</button>
        </div>
      </section>
    </div>
  )
}

function ChoiceBox({ title, items, selected, onChange, searchable = false }: { title: string; items: Choice[]; selected: string[]; onChange: (items: string[]) => void; searchable?: boolean }) {
  const [q, setQ] = useState('')
  const shown = (q ? items.filter((item) => item.label.toLowerCase().includes(q.toLowerCase())) : items).slice(0, searchable && !q ? 30 : 60)
  const toggle = (value: string) => onChange(selected.includes(value) ? selected.filter((item) => item !== value) : [...selected, value])

  return (
    <div>
      <div className="mb-3 text-xs font-bold text-slate-400">{title}</div>
      {searchable && <input className="input mb-3 py-2" value={q} onChange={(event) => setQ(event.target.value)} placeholder={`搜索${title}`} />}
      <div className="max-h-48 overflow-y-auto rounded-2xl border border-white/10 bg-black/10 p-3">
        <div className="flex flex-wrap gap-2">
          {shown.map((item) => (
            <button key={item.value} className={`chip ${selected.includes(item.value) ? 'border-neon/50 bg-neon/15 text-neon' : ''}`} onClick={() => toggle(item.value)}>
              {item.label}
            </button>
          ))}
        </div>
      </div>
    </div>
  )
}

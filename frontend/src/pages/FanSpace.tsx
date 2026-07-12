import { useEffect, useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { BellRing, Heart, RefreshCw, Save, Sparkles, X } from 'lucide-react'
import SectionHeader from '../components/SectionHeader'
import { ErrorBox, Loading } from '../components/Loading'
import { getDaily, getPlayers, getTeams, getUsers, saveUser } from '../lib/api'
import { activeUserFrom } from '../lib/session'
import { labelWithFlag } from '../lib/teamFlags'

type Choice = { value: string; label: string }

const contentTypes = ['综合', '比赛内容', '战术分析', '球队动态', '球迷内容', '娱乐内容', '二创内容', '短视频']

export default function FanSpace() {
  const qc = useQueryClient()
  const teams = useQuery({ queryKey: ['teams'], queryFn: getTeams })
  const players = useQuery({ queryKey: ['players'], queryFn: () => getPlayers({ legends: false }) })
  const users = useQuery({ queryKey: ['users'], queryFn: getUsers })
  const active = activeUserFrom(users.data)
  const [form, setForm] = useState({
    username: '我的球迷档案',
    favorite_teams: [] as string[],
    favorite_players: [] as string[],
    dislike_teams: [] as string[],
    favorite_content_type: '综合',
    onboarding_completed: true,
  })

  useEffect(() => {
    if (active) {
      setForm({
        username: active.username,
        favorite_teams: active.favorite_teams,
        favorite_players: active.favorite_players,
        dislike_teams: active.dislike_teams,
        favorite_content_type: active.favorite_content_type,
        onboarding_completed: true,
      })
    }
  }, [active])

  const daily = useQuery({ queryKey: ['daily', active?.id], queryFn: () => getDaily(active!.id), enabled: !!active, refetchInterval: 60_000 })
  const save = useMutation({ mutationFn: () => saveUser(form), onSuccess: async (user) => { await qc.invalidateQueries({ queryKey: ['users'] }); await qc.invalidateQueries({ queryKey: ['daily', user.id] }) } })
  const refresh = useMutation({ mutationFn: () => getDaily(active!.id, true), onSuccess: (data) => qc.setQueryData(['daily', active?.id], data) })
  const toggle = (key: 'favorite_teams' | 'favorite_players' | 'dislike_teams', name: string) => setForm((value) => ({ ...value, [key]: value[key].includes(name) ? value[key].filter((item) => item !== name) : [...value[key], name] }))
  const setPrimaryTeam = (name: string) => setForm((value) => ({ ...value, favorite_teams: name ? [name, ...value.favorite_teams.filter((item) => item !== name)] : value.favorite_teams }))

  const teamChoices = useMemo<Choice[]>(() => (teams.data ?? []).map((team) => ({ value: team.name, label: `${team.flag} ${team.name}` })), [teams.data])
  const playerChoices = useMemo<Choice[]>(() => (players.data ?? []).map((player) => ({
    value: player.name,
    label: `${player.flag} ${player.name}${player.english_name !== player.name ? ` / ${player.english_name}` : ''}`,
  })), [players.data])

  if (teams.isLoading || players.isLoading || users.isLoading) return <Loading />
  if (teams.isError || players.isError || users.isError) return <ErrorBox />

  return (
    <div className="space-y-8">
      <section className="relative overflow-hidden rounded-[32px] border border-white/10 bg-gradient-to-br from-neon/15 via-white/[.04] to-cyanai/10 p-6 md:p-8">
        <div className="absolute right-8 top-2 text-[130px] opacity-10">🏟️</div>
        <div className="relative max-w-3xl">
          <div className="eyebrow">MY FAN SPACE</div>
          <h1 className="mt-3 bg-gradient-to-r from-neon via-cyanai to-amber-200 bg-clip-text text-3xl font-black text-transparent md:text-5xl">
            {active?.username ?? form.username}
          </h1>
          <div className="mt-5 flex flex-wrap gap-2">
            {active?.favorite_teams.map((team) => <span className="chip" key={team}>❤️ {labelWithFlag(team, teams.data)}</span>)}
            {active?.favorite_players.map((player) => <span className="chip" key={player}>⭐ {player}</span>)}
          </div>
        </div>
      </section>

      <div className="grid gap-6 xl:grid-cols-[.82fr_1.18fr]">
        <section className="panel p-5 md:p-6">
          <SectionHeader eyebrow="FAN PROFILE" title="编辑画像" />
          <label className="mb-5 block">
            <span className="mb-2 block text-xs font-bold text-slate-400">用户名</span>
            <input className="input opacity-70" value={form.username} disabled />
          </label>
          <label className="mb-5 block">
            <span className="mb-2 block text-xs font-bold text-slate-400">主队</span>
            <select className="input" value={form.favorite_teams[0] ?? ''} onChange={(event) => setPrimaryTeam(event.target.value)}>
              <option value="">暂不选择</option>
              {teamChoices.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
            </select>
          </label>
          <Picker title="支持球队" icon="❤️" items={teamChoices} selected={form.favorite_teams} onToggle={(item) => toggle('favorite_teams', item)} />
          <Picker title="支持球员" icon="⭐" items={playerChoices} selected={form.favorite_players} onToggle={(item) => toggle('favorite_players', item)} />
          <Picker title="屏蔽球队消息" icon="⛔" items={teamChoices} selected={form.dislike_teams} onToggle={(item) => toggle('dislike_teams', item)} />
          <div className="mb-6">
            <div className="mb-3 text-xs font-bold text-slate-400">内容偏好</div>
            <div className="flex flex-wrap gap-2">
              {contentTypes.map((item) => <button key={item} onClick={() => setForm({ ...form, favorite_content_type: item })} className={`chip ${form.favorite_content_type === item ? 'border-neon/50 bg-neon/15 text-neon' : ''}`}>{item}</button>)}
            </div>
          </div>
          <button className="btn-primary w-full" onClick={() => save.mutate()} disabled={save.isPending}><Save size={17} />{save.isPending ? '正在保存' : '保存画像'}</button>
        </section>

        <section>
          <SectionHeader eyebrow="PERSONAL DAILY" title="世界杯日报" action={<button className="btn-secondary" onClick={() => refresh.mutate()} disabled={!active || refresh.isPending}><RefreshCw size={16} className={refresh.isPending ? 'animate-spin' : ''} />刷新日报</button>} />
          {daily.isLoading ? <Loading /> : (
            <div className="space-y-3">
              {(daily.data ?? []).sort((a, b) => b.priority - a.priority).map((item, index) => (
                <div key={item.id} className={`panel p-5 transition hover:border-neon/20 ${index === 0 ? 'border-neon/25 bg-neon/[.07]' : ''}`}>
                  <div className="flex items-start gap-4">
                    <div className={`grid h-11 w-11 shrink-0 place-items-center rounded-2xl ${item.category === '比赛提醒' ? 'bg-amber-300/10 text-amber-200' : item.category === '竞争动态' ? 'bg-red-400/10 text-red-300' : 'bg-neon/10 text-neon'}`}>
                      {item.category === '比赛提醒' ? <BellRing size={19} /> : item.category === '日报导语' ? <Sparkles size={19} /> : <Heart size={19} />}
                    </div>
                    <div>
                      <div className="mb-1 text-[10px] font-bold uppercase tracking-widest text-slate-500">{item.category} · PRIORITY {item.priority}</div>
                      <h3 className="font-black">{item.title}</h3>
                      <p className="mt-2 text-sm leading-6 text-slate-400">{item.content}</p>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </section>
      </div>
    </div>
  )
}

function Picker({ title, icon, items, selected, onToggle }: { title: string; icon: string; items: Choice[]; selected: string[]; onToggle: (value: string) => void }) {
  const [open, setOpen] = useState(false)
  const [q, setQ] = useState('')
  const filtered = q ? items.filter((item) => item.label.toLowerCase().includes(q.toLowerCase())) : items
  const shown = open || q ? filtered : filtered.slice(0, 12)
  return (
    <div className="mb-6">
      <div className="mb-3 flex items-center justify-between">
        <span className="text-xs font-bold text-slate-400">{icon} {title}</span>
        <button className="text-[11px] text-neon" onClick={() => setOpen(!open)}>{open ? '收起' : '显示全部'}</button>
      </div>
      <input className="input mb-3 py-2" value={q} onChange={(event) => setQ(event.target.value)} placeholder={`搜索${title}`} />
      <div className="flex flex-wrap gap-2">
        {shown.map((item) => (
          <button key={item.value} onClick={() => onToggle(item.value)} className={`chip ${selected.includes(item.value) ? 'border-neon/50 bg-neon/15 text-neon' : ''}`}>
            {item.label}{selected.includes(item.value) && <X size={11} />}
          </button>
        ))}
      </div>
    </div>
  )
}

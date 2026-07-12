import { FormEvent, useEffect, useRef, useState } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import {
  Bot,
  BrainCircuit,
  CalendarDays,
  Download,
  ExternalLink,
  Image as ImageIcon,
  MessageSquareText,
  Newspaper,
  Palette,
  Send,
  Sparkles,
  Trophy,
  UserRound,
  WandSparkles,
} from 'lucide-react'
import { useSearchParams } from 'react-router-dom'
import AIText from '../components/AIText'
import SectionHeader from '../components/SectionHeader'
import { generateContent, getTeams, getUsers, sendChat, staticUrl } from '../lib/api'
import { activeUserFrom } from '../lib/session'
import type { ChatResponse } from '../types'

type Message = { role: 'user' | 'assistant'; content: string; response?: ChatResponse }

const quick = ['我支持的球队夺冠概率', '某支球队的战术特点', '某位球员近期状态', '这条世界杯新闻可信吗？']
const tonePresets = ['热血', '克制高级', '幽默', '电影感', '青春']
const modules = [
  { title: '比赛预测', desc: '概率 / 胜率', icon: Trophy, prompt: '我支持的球队夺冠概率' },
  { title: '球队球员', desc: '战术 / 状态', icon: CalendarDays, prompt: '某支球队的战术特点' },
  { title: '新闻核验', desc: '来源 / 风险', icon: Newspaper, prompt: '帮我判断一条新闻真假' },
  { title: '内容创作', desc: '海报 / 文案', icon: Palette, prompt: '生成我支持球队的海报文案' },
]

export default function AIStudio() {
  const [params] = useSearchParams()
  const users = useQuery({ queryKey: ['users'], queryFn: getUsers })
  const activeUser = activeUserFrom(users.data)
  const teams = useQuery({ queryKey: ['teams'], queryFn: getTeams })
  const [tab, setTab] = useState<'chat' | 'create'>('chat')
  const [input, setInput] = useState('')
  const [messages, setMessages] = useState<Message[]>([
    { role: 'assistant', content: '直接输入球队、球员、新闻或比赛问题。' },
  ])
  const bottom = useRef<HTMLDivElement>(null)
  const chat = useMutation({
    mutationFn: (message: string) => sendChat(message, activeUser?.id),
    onSuccess: (response) => setMessages((items) => [...items, { role: 'assistant', content: response.answer, response }]),
  })
  const [gen, setGen] = useState({ topic: '', team: '', player: '', tone: '热血', generate_image: false })
  const create = useMutation({ mutationFn: () => generateContent(gen) })
  const createError = create.error instanceof Error ? create.error.message : '生成失败，请稍后重试'

  const submit = (event?: FormEvent) => {
    event?.preventDefault()
    const text = input.trim()
    if (!text || chat.isPending) return
    setMessages((items) => [...items, { role: 'user', content: text }])
    setInput('')
    chat.mutate(text)
  }

  useEffect(() => {
    const q = params.get('q')
    if (q) setInput(q)
  }, [params])

  useEffect(() => {
    bottom.current?.scrollIntoView({ behavior: 'smooth' })
  }, [messages, chat.isPending])

  return (
    <div className="space-y-7">
      <section className="rounded-[32px] border border-white/10 bg-gradient-to-br from-neon/15 via-[#0a1516] to-cyanai/10 p-6 md:p-9">
        <div className="eyebrow">AI FOOTBALL BRAIN</div>
        <h1 className="mt-3 bg-gradient-to-r from-neon via-cyanai to-amber-200 bg-clip-text text-3xl font-black text-transparent md:text-5xl">
          AI Studio
        </h1>
      </section>

      <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        {modules.map(({ title, desc, icon: Icon, prompt }) => (
          <button
            key={title}
            onClick={() => {
              if (title === '内容创作') {
                setTab('create')
                setGen((value) => ({ ...value, topic: prompt }))
              } else {
                setTab('chat')
                setInput(prompt)
              }
            }}
            className="rounded-3xl border border-white/10 bg-white/[.035] p-4 text-left transition hover:border-neon/30 hover:bg-white/[.06]"
          >
            <div className="mb-4 grid h-10 w-10 place-items-center rounded-2xl bg-neon/10 text-neon"><Icon size={19} /></div>
            <div className="font-black">{title}</div>
            <div className="mt-1 text-xs text-slate-500">{desc}</div>
          </button>
        ))}
      </div>

      <div className="flex gap-2">
        <button onClick={() => setTab('chat')} className={`flex items-center gap-2 rounded-2xl px-5 py-3 text-sm font-bold ${tab === 'chat' ? 'bg-neon text-pitch' : 'panel'}`}><MessageSquareText size={17} />AI 分析</button>
        <button onClick={() => setTab('create')} className={`flex items-center gap-2 rounded-2xl px-5 py-3 text-sm font-bold ${tab === 'create' ? 'bg-neon text-pitch' : 'panel'}`}><WandSparkles size={17} />AIGC 创作</button>
      </div>

      {tab === 'chat' ? (
        <div className="grid gap-6 xl:grid-cols-[1fr_300px]">
          <section className="panel flex min-h-[650px] flex-col overflow-hidden">
            <div className="border-b border-white/10 p-4">
              <div className="flex items-center gap-3">
                <div className="grid h-10 w-10 place-items-center rounded-2xl bg-neon text-pitch"><BrainCircuit size={20} /></div>
                <div>
                  <div className="font-black">WorldCup AI</div>
                  <div className="text-[10px] text-slate-500">MULTI-AGENT ONLINE</div>
                </div>
              </div>
            </div>
            <div className="flex-1 space-y-5 overflow-y-auto p-4 md:p-6">
              {messages.map((message, index) => (
                <div key={index} className={`flex gap-3 ${message.role === 'user' ? 'justify-end' : ''}`}>
                  {message.role === 'assistant' && <div className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-neon/10 text-neon"><Bot size={17} /></div>}
                  <div className={`max-w-[86%] rounded-3xl px-5 py-4 text-sm leading-7 ${message.role === 'user' ? 'bg-neon text-pitch' : 'border border-white/10 bg-white/[.04] text-slate-200'}`}>
                    {message.role === 'assistant' ? <AIText text={message.content} /> : <div className="whitespace-pre-wrap">{message.content}</div>}
                    {message.response && (
                      <>
                        <div className="mt-4 flex flex-wrap gap-2 border-t border-white/10 pt-3 text-[10px]">
                          <span className="chip">{message.response.agent}</span>
                          <span className="chip">{message.response.model_mode === 'ark' ? 'ARK 增强' : '本地模式'}</span>
                          <span className="chip">{message.response.route_reason}</span>
                        </div>
                        {typeof message.response.metadata.poster_url === 'string' && message.response.metadata.poster_url && <img className="mt-4 max-h-[460px] rounded-2xl border border-white/10" src={staticUrl(message.response.metadata.poster_url)} alt="AI海报" />}
                        {typeof message.response.metadata.image_error === 'string' && message.response.metadata.image_error && (
                          <div className="mt-4 rounded-2xl border border-amber-300/25 bg-amber-300/10 p-3 text-xs leading-5 text-amber-100">
                            {message.response.metadata.image_error}
                          </div>
                        )}
                        {message.response.suggested_questions.length > 0 && (
                          <div className="mt-4 flex flex-wrap gap-2">
                            {message.response.suggested_questions.map((item) => <button onClick={() => setInput(item)} key={item} className="chip">{item}</button>)}
                          </div>
                        )}
                      </>
                    )}
                  </div>
                  {message.role === 'user' && <div className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-cyanai/10 text-cyanai"><UserRound size={17} /></div>}
                </div>
              ))}
              {chat.isPending && (
                <div className="flex gap-3">
                  <div className="grid h-9 w-9 place-items-center rounded-xl bg-neon/10 text-neon"><Bot size={17} /></div>
                  <div className="rounded-3xl border border-white/10 bg-white/[.04] px-5 py-4">
                    <span className="inline-flex gap-1">
                      <i className="h-2 w-2 animate-bounce rounded-full bg-neon" />
                      <i className="h-2 w-2 animate-bounce rounded-full bg-neon [animation-delay:120ms]" />
                      <i className="h-2 w-2 animate-bounce rounded-full bg-neon [animation-delay:240ms]" />
                    </span>
                  </div>
                </div>
              )}
              <div ref={bottom} />
            </div>
            <form onSubmit={submit} className="border-t border-white/10 p-4">
              <div className="flex gap-3">
                <textarea
                  className="input min-h-14 flex-1 resize-none"
                  rows={2}
                  placeholder="输入球队、球员、新闻或比赛问题..."
                  value={input}
                  onChange={(event) => setInput(event.target.value)}
                  onKeyDown={(event) => {
                    if (event.key === 'Enter' && !event.shiftKey) {
                      event.preventDefault()
                      submit()
                    }
                  }}
                />
                <button className="btn-primary px-5" disabled={!input.trim() || chat.isPending}><Send size={18} /></button>
              </div>
            </form>
          </section>

          <aside className="space-y-5">
            <div className="panel p-5">
              <SectionHeader eyebrow="TRY ASKING" title="示例" />
              {quick.map((item) => (
                <button key={item} onClick={() => setInput(item)} className="mb-2 w-full rounded-2xl border border-white/10 bg-white/[.035] p-3 text-left text-sm text-slate-300 transition hover:border-neon/30 hover:text-neon">
                  {item}
                </button>
              ))}
            </div>
            <div className="panel p-5">
              <div className="mb-3 text-xs font-black text-neon">球队入口</div>
              <div className="flex flex-wrap gap-2">
                {teams.data?.slice(0, 10).map((team) => (
                  <button key={team.id} onClick={() => setInput(`${team.name}战术特点`)} className="chip">{team.flag} {team.name}</button>
                ))}
              </div>
            </div>
          </aside>
        </div>
      ) : (
        <section className="grid gap-6 xl:grid-cols-[.72fr_1.28fr]">
          <div className="panel p-6">
            <SectionHeader eyebrow="CREATIVE BRIEF" title="创作参数" />
            <label className="mb-4 block">
              <span className="mb-2 block text-xs font-bold text-slate-400">主题</span>
              <input className="input" placeholder="例如：我支持的球队冲击冠军" value={gen.topic} onChange={(event) => setGen({ ...gen, topic: event.target.value })} />
            </label>
            <label className="mb-4 block">
              <span className="mb-2 block text-xs font-bold text-slate-400">球队</span>
              <input
                className="input"
                placeholder="可选：输入任意球队名，例如 阿根廷、England、迈阿密国际"
                value={gen.team}
                onChange={(event) => setGen({ ...gen, team: event.target.value })}
              />
            </label>
            <div className="mb-4 flex gap-2 overflow-x-auto pb-1">
              <button type="button" className={`chip shrink-0 ${!gen.team ? 'border-neon/50 bg-neon/15 text-neon' : ''}`} onClick={() => setGen({ ...gen, team: '' })}>
                不指定
              </button>
              {teams.data?.slice(0, 16).map((team) => (
                <button
                  type="button"
                  key={team.id}
                  className={`chip shrink-0 ${gen.team === team.name ? 'border-neon/50 bg-neon/15 text-neon' : ''}`}
                  onClick={() => setGen({ ...gen, team: team.name })}
                >
                  {team.flag} {team.name}
                </button>
              ))}
            </div>
            <label className="mb-4 block">
              <span className="mb-2 block text-xs font-bold text-slate-400">球员</span>
              <input className="input" placeholder="可选：输入球员名" value={gen.player} onChange={(event) => setGen({ ...gen, player: event.target.value })} />
            </label>
            <label className="mb-4 block">
              <span className="mb-2 block text-xs font-bold text-slate-400">语气</span>
              <input
                className="input"
                placeholder="例如：冷静专业、像球迷论坛热帖、燃一点但别夸张"
                value={gen.tone}
                onChange={(event) => setGen({ ...gen, tone: event.target.value })}
              />
            </label>
            <div className="mb-6 flex flex-wrap gap-2">
              {tonePresets.map((item) => (
                <button
                  key={item}
                  type="button"
                  className={`chip ${gen.tone === item ? 'border-neon/50 bg-neon/15 text-neon' : ''}`}
                  onClick={() => setGen({ ...gen, tone: item })}
                >
                  {item}
                </button>
              ))}
            </div>
            <label className="mb-4 flex items-center gap-3 rounded-2xl border border-white/10 bg-white/[.035] p-4 text-sm text-slate-300">
              <input
                type="checkbox"
                checked={gen.generate_image}
                onChange={(event) => setGen({ ...gen, generate_image: event.target.checked })}
                className="h-4 w-4 accent-[#3fffb5]"
              />
              <span>同时生成 AI 图片，消耗 1 张 Seedream 额度</span>
            </label>
            <button className="btn-primary w-full" onClick={() => create.mutate()} disabled={create.isPending || !gen.topic.trim()}>
              <Sparkles size={17} />{create.isPending ? '生成中' : gen.generate_image ? '生成内容和图片' : '生成内容'}
            </button>
            {create.isError && (
              <div className="mt-4 rounded-2xl border border-red-400/30 bg-red-500/10 p-3 text-sm leading-6 text-red-100">
                生成失败：{createError}
              </div>
            )}
          </div>
          <div className="space-y-5">
            {!create.data ? (
              <div className="panel grid min-h-[520px] place-items-center p-8 text-center">
                <div>
                  <ImageIcon className="mx-auto mb-4 text-neon" size={40} />
                  <h3 className="text-xl font-black">填写主题开始创作</h3>
                </div>
              </div>
            ) : (
              <>
                <div className="grid gap-5 lg:grid-cols-[.65fr_1.35fr]">
                  <div className="space-y-3">
                    {create.data.poster_url ? (
                      <>
                        <img src={staticUrl(create.data.poster_url)} className="w-full rounded-3xl border border-white/10" alt="生成海报" />
                        <div className="flex flex-wrap gap-2">
                          <a className="chip" href={staticUrl(create.data.poster_url)} target="_blank" rel="noreferrer"><ExternalLink size={14} />打开原图</a>
                          <a className="chip" href={staticUrl(create.data.poster_url)} download><Download size={14} />下载海报</a>
                        </div>
                      </>
                    ) : (
                      <div className="panel flex min-h-[420px] items-center justify-center p-6 text-center text-sm leading-7 text-amber-100">
                        {create.data.image_error || '未返回图片，请检查图片生成模型配置。'}
                      </div>
                    )}
                  </div>
                  <div className="space-y-5">
                    <Output title="朋友圈文案" text={create.data.social_copy} />
                    <Output title="海报 Prompt" text={create.data.poster_prompt} />
                  </div>
                </div>
                <Output title="30秒视频脚本" text={create.data.video_script} />
                <div className="panel p-5">
                  <div className="mb-3 text-sm font-black">口号</div>
                  <div className="flex flex-wrap gap-2">{create.data.slogans.map((item: string) => <span className="chip" key={item}>{item}</span>)}</div>
                </div>
              </>
            )}
          </div>
        </section>
      )}
    </div>
  )
}

function Output({ title, text }: { title: string; text: string }) {
  return <div className="panel p-5"><div className="mb-3 text-sm font-black text-neon">{title}</div><AIText text={text} className="text-sm leading-7 text-slate-300" /></div>
}

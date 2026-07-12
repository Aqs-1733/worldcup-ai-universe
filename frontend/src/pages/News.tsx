import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ExternalLink, RefreshCw, Search, ShieldAlert, ShieldCheck, Sparkles, X } from 'lucide-react'
import SectionHeader from '../components/SectionHeader'
import { ErrorBox, Loading } from '../components/Loading'
import { analyzeNews, getNews, getNewsDetail, getTeams, refreshNews } from '../lib/api'
import { labelWithFlag } from '../lib/teamFlags'

const categories = ['全部', '比赛内容', '战术分析', '球队动态', '球迷内容', '娱乐内容', '二创内容', '世界杯', '足球', '技术']

export default function News() {
  const qc = useQueryClient()
  const [q, setQ] = useState('')
  const [category, setCategory] = useState('全部')
  const [form, setForm] = useState({ title: '', content: '', source: '未知来源' })
  const [selectedId, setSelectedId] = useState<number | null>(null)
  const news = useQuery({
    queryKey: ['news-all', q, category],
    queryFn: () => getNews(q, 120, category === '全部' ? '' : category),
    refetchInterval: 30_000,
  })
  const teams = useQuery({ queryKey: ['teams'], queryFn: getTeams })
  const detail = useQuery({
    queryKey: ['news-detail', selectedId],
    queryFn: () => getNewsDetail(selectedId as number),
    enabled: selectedId !== null,
  })
  const refresh = useMutation({ mutationFn: refreshNews, onSuccess: () => qc.invalidateQueries({ queryKey: ['news-all'] }) })
  const analyze = useMutation({ mutationFn: () => analyzeNews(form) })
  const filtered = news.data ?? []
  const refreshStats = refresh.data as
    | { collected?: number; inserted?: number; duplicates?: number; failures?: unknown[]; skipped?: string }
    | undefined

  if (news.isLoading) return <Loading />
  if (news.isError) return <ErrorBox />

  return (
    <div className="space-y-8">
      <section className="rounded-[32px] border border-white/10 bg-gradient-to-br from-[#171f2d] via-[#0a1516] to-[#102a21] p-6 md:p-9">
        <div className="eyebrow">NEWS INTELLIGENCE</div>
        <h1 className="mt-3 bg-gradient-to-r from-neon via-cyanai to-amber-200 bg-clip-text text-3xl font-black text-transparent md:text-5xl">
          新闻中心
        </h1>
      </section>

      <section className="grid gap-6 xl:grid-cols-[1.2fr_.8fr]">
        <div>
          <div className="panel mb-5 flex flex-col gap-3 p-4 sm:flex-row">
            <div className="relative flex-1">
              <Search className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-500" size={17} />
              <input className="input pl-11" value={q} onChange={(event) => setQ(event.target.value)} placeholder="搜索新闻、球队、球员或来源" />
            </div>
            <button onClick={() => refresh.mutate()} disabled={refresh.isPending} className="btn-primary">
              <RefreshCw size={16} className={refresh.isPending ? 'animate-spin' : ''} />{refresh.isPending ? '采集中' : '联网刷新'}
            </button>
          </div>
          <div className="mb-5 flex gap-2 overflow-x-auto pb-1">
            {categories.map((item) => <button key={item} onClick={() => setCategory(item)} className={`chip shrink-0 ${category === item ? 'border-neon/50 bg-neon/15 text-neon' : ''}`}>{item}</button>)}
          </div>
          {refreshStats && (
            <div className="mb-4 rounded-2xl border border-neon/15 bg-neon/[.05] p-4 text-sm leading-6 text-slate-300">
              {refreshStats.skipped === 'refresh_in_progress'
                ? '已有一次联网采集正在进行，请稍后刷新列表。'
                : `本次采集 ${refreshStats.collected ?? 0} 条，新增 ${refreshStats.inserted ?? 0} 条，重复 ${refreshStats.duplicates ?? 0} 条；失败源 ${refreshStats.failures?.length ?? 0} 个。`}
            </div>
          )}
          <SectionHeader title={`${filtered.length} 条新闻`} />
          <div className="space-y-4">
            {filtered.map((item) => {
              const itemCategories = item.categories?.length ? item.categories : [item.category]
              return (
                <article key={item.id} className="panel p-5 transition hover:border-neon/20">
                  <div className="flex items-start gap-4">
                    <div className={`grid h-12 w-12 shrink-0 place-items-center rounded-2xl ${item.credibility_score >= 85 ? 'bg-neon/10 text-neon' : item.credibility_score >= 68 ? 'bg-cyanai/10 text-cyanai' : 'bg-amber-300/10 text-amber-200'}`}>
                      {item.credibility_score >= 68 ? <ShieldCheck size={20} /> : <ShieldAlert size={20} />}
                    </div>
                    <div className="min-w-0 flex-1">
                      <div className="mb-2 flex flex-wrap items-center gap-2 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                        {itemCategories.map((name) => <span key={name} className="rounded-full border border-white/10 px-2 py-1 text-neon">{name}</span>)}
                        <span>·</span><span>{item.source}</span><span>·</span><span>{new Date(item.published_at).toLocaleString('zh-CN')}</span>
                      </div>
                      <h3 className="text-lg font-black leading-7">{item.title}</h3>
                      <p className="mt-2 text-sm leading-6 text-slate-400">{item.summary || '该来源未提供摘要，请打开原文查看。'}</p>
                      <div className="mt-4 flex flex-wrap items-center gap-2">
                        <span className={`chip ${item.credibility_score >= 85 ? 'border-neon/30 text-neon' : item.credibility_score >= 68 ? 'border-cyanai/30 text-cyanai' : 'border-amber-300/30 text-amber-200'}`}>可信度 {item.credibility_score}% · {item.credibility_label}</span>
                        {item.related_teams.map((team) => <span key={team} className="chip">{labelWithFlag(team, teams.data)}</span>)}
                        <button className="chip ml-auto" onClick={() => setSelectedId(item.id)}>阅读全文</button>
                        {item.source_url && <a className="chip" target="_blank" rel="noreferrer" href={item.source_url}>来源 <ExternalLink size={12} /></a>}
                      </div>
                    </div>
                  </div>
                </article>
              )
            })}
          </div>
        </div>

        <aside className="space-y-5">
          <div className="panel sticky top-24 p-5 md:p-6">
            <SectionHeader eyebrow="FACT CHECK" title="新闻检测" />
            <label className="mb-3 block">
              <span className="mb-2 block text-xs font-bold text-slate-400">新闻标题</span>
              <input className="input" value={form.title} onChange={(event) => setForm({ ...form, title: event.target.value })} placeholder="例如：某球队已百分百夺冠" />
            </label>
            <label className="mb-3 block">
              <span className="mb-2 block text-xs font-bold text-slate-400">来源</span>
              <input className="input" value={form.source} onChange={(event) => setForm({ ...form, source: event.target.value })} />
            </label>
            <label className="mb-4 block">
              <span className="mb-2 block text-xs font-bold text-slate-400">正文</span>
              <textarea className="input min-h-32 resize-y" value={form.content} onChange={(event) => setForm({ ...form, content: event.target.value })} placeholder="可选：粘贴新闻正文或摘要" />
            </label>
            <button className="btn-primary w-full" disabled={form.title.length < 3 || analyze.isPending} onClick={() => analyze.mutate()}><Sparkles size={16} />{analyze.isPending ? '正在核验' : '开始核验'}</button>
            {analyze.data && (
              <div className="mt-5 rounded-2xl border border-white/10 bg-black/20 p-5">
                <div className="flex items-center justify-between"><span className="text-sm font-bold">核验结论</span><span className="text-3xl font-black text-neon">{analyze.data.credibility_score}%</span></div>
                <div className="mt-2 text-lg font-black">{analyze.data.judgement}</div>
                <ul className="mt-3 space-y-2 text-xs leading-5 text-slate-400">{analyze.data.notes?.map((item: string) => <li key={item}>• {item}</li>)}</ul>
                {analyze.data.ai_explanation && <p className="mt-4 border-t border-white/10 pt-4 text-sm leading-6 text-slate-300">{analyze.data.ai_explanation}</p>}
              </div>
            )}
          </div>
        </aside>
      </section>

      {selectedId !== null && (
        <div className="fixed inset-0 z-50 overflow-y-auto bg-black/70 p-4 backdrop-blur-sm" onClick={() => setSelectedId(null)}>
          <article className="mx-auto my-6 max-h-[calc(100vh-3rem)] w-full max-w-4xl overflow-y-auto rounded-[30px] border border-white/10 bg-[#07110f] p-5 shadow-[0_30px_90px_rgba(0,0,0,.45)] md:p-7" onClick={(event) => event.stopPropagation()}>
            <div className="mb-5 flex items-start justify-between gap-4">
              <div>
                <div className="eyebrow">SOURCE DETAIL</div>
                <h2 className="mt-2 text-2xl font-black leading-9">
                  {detail.data?.translated_title || detail.data?.title || '正在读取新闻'}
                </h2>
                {detail.data && (
                  <div className="mt-3 flex flex-wrap gap-2 text-xs text-slate-500">
                    <span>{detail.data.source}</span>
                    <span>{new Date(detail.data.published_at).toLocaleString('zh-CN')}</span>
                    <span>{detail.data.language === 'zh' ? '中文原文' : detail.data.translated_content ? '已翻译' : '原文'}</span>
                  </div>
                )}
              </div>
              <button className="rounded-xl border border-white/10 p-2" onClick={() => setSelectedId(null)}><X size={18} /></button>
            </div>
            {detail.isLoading && <Loading label="正在读取来源正文" />}
            {detail.isError && <ErrorBox />}
            {detail.data && (
              <div className="space-y-5">
                <div className="eyebrow">中文译文</div>
                {(detail.data.translated_summary || detail.data.summary) && (
                  <p className="rounded-2xl border border-neon/15 bg-neon/[.05] p-4 text-sm leading-7 text-slate-200">
                    {detail.data.translated_summary || detail.data.summary}
                  </p>
                )}
                <div className="whitespace-pre-wrap text-sm leading-8 text-slate-300">
                  {detail.data.translated_content || detail.data.content || detail.data.summary || '来源页面没有返回可抽取正文。'}
                </div>
                {(detail.data.original_title || detail.data.original_summary || detail.data.content) && (
                  <div className="rounded-2xl border border-white/10 bg-black/15 p-4">
                    <div className="mb-3 text-xs font-black uppercase tracking-widest text-slate-500">原文版本</div>
                    {detail.data.original_title && <h3 className="font-black leading-7 text-slate-200">{detail.data.original_title}</h3>}
                    {detail.data.original_summary && <p className="mt-3 text-sm leading-7 text-slate-400">{detail.data.original_summary}</p>}
                    {detail.data.content && <div className="mt-4 whitespace-pre-wrap border-t border-white/10 pt-4 text-xs leading-6 text-slate-500">{detail.data.content}</div>}
                  </div>
                )}
                {detail.data.source_url && (
                  <a className="btn-secondary" href={detail.data.source_url} target="_blank" rel="noreferrer">
                    打开来源 <ExternalLink size={14} />
                  </a>
                )}
              </div>
            )}
          </article>
        </div>
      )}
    </div>
  )
}

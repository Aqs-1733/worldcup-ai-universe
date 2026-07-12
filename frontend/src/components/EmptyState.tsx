import { Sparkles } from 'lucide-react'
export default function EmptyState({title='暂无数据',text='可以调整筛选条件后重试'}:{title?:string;text?:string}){return <div className="panel grid min-h-[220px] place-items-center p-8 text-center"><div><Sparkles className="mx-auto mb-3 text-neon"/><h3 className="font-black">{title}</h3><p className="muted mt-2">{text}</p></div></div>}

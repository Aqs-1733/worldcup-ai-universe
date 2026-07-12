import type { ReactNode } from 'react'
export default function SectionHeader({eyebrow,title,description,action}:{eyebrow?:string;title:string;description?:string;action?:ReactNode}){
 return <div className="mb-5 flex flex-col justify-between gap-3 sm:flex-row sm:items-end"><div>{eyebrow&&<div className="eyebrow mb-2">{eyebrow}</div>}<h2 className="section-title">{title}</h2>{description&&<p className="muted mt-2 max-w-2xl">{description}</p>}</div>{action}</div>
}

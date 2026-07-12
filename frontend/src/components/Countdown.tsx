import { useEffect, useState } from 'react'

type CountdownKey = 'days' | 'hours' | 'minutes' | 'seconds'
const labels: Record<CountdownKey, string> = { days: '天', hours: '时', minutes: '分', seconds: '秒' }
function diff(target: string): Record<CountdownKey, number> {
  const delta = Math.max(0, new Date(target).getTime() - Date.now())
  return {
    days: Math.floor(delta / 86400000),
    hours: Math.floor(delta / 3600000) % 24,
    minutes: Math.floor(delta / 60000) % 60,
    seconds: Math.floor(delta / 1000) % 60,
  }
}
export default function Countdown({ target }: { target: string }) {
  const [time, setTime] = useState(() => diff(target))
  useEffect(() => {
    const id = setInterval(() => setTime(diff(target)), 1000)
    return () => clearInterval(id)
  }, [target])
  return <div className="grid grid-cols-4 gap-2 md:gap-3">
    {(Object.entries(time) as Array<[CountdownKey, number]>).map(([key, value]) => <div key={key} className="rounded-2xl border border-white/10 bg-black/20 px-2 py-4 text-center">
      <div className="text-2xl font-black tabular-nums md:text-4xl">{String(value).padStart(2, '0')}</div>
      <div className="mt-1 text-[10px] uppercase tracking-widest text-slate-500">{labels[key]}</div>
    </div>)}
  </div>
}

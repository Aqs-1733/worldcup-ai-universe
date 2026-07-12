import { Home } from 'lucide-react'
import { Link } from 'react-router-dom'
export default function NotFound(){return <div className="grid min-h-[70vh] place-items-center text-center"><div><div className="text-8xl font-black text-neon">404</div><h1 className="mt-3 text-2xl font-black">这脚球踢出了边线</h1><p className="muted mt-2">页面不存在，返回首页继续探索世界杯宇宙。</p><Link to="/" className="btn-primary mt-6"><Home size={17}/>返回首页</Link></div></div>}

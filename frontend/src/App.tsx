import { Route, Routes } from 'react-router-dom'
import Layout from './components/Layout'
import Home from './pages/Home'
import FanSpace from './pages/FanSpace'
import WorldCup from './pages/WorldCup'
import Teams from './pages/Teams'
import TeamDetail from './pages/TeamDetail'
import Players from './pages/Players'
import PlayerDetail from './pages/PlayerDetail'
import News from './pages/News'
import AIStudio from './pages/AIStudio'
import Vision from './pages/Vision'
import NotFound from './pages/NotFound'

export default function App(){return <Routes><Route element={<Layout/>}>
  <Route index element={<Home/>}/><Route path="fan-space" element={<FanSpace/>}/><Route path="worldcup" element={<WorldCup/>}/>
  <Route path="teams" element={<Teams/>}/><Route path="teams/:slug" element={<TeamDetail/>}/>
  <Route path="players" element={<Players/>}/><Route path="players/:slug" element={<PlayerDetail/>}/>
  <Route path="news" element={<News/>}/><Route path="ai-studio" element={<AIStudio/>}/><Route path="vision" element={<Vision/>}/>
  <Route path="*" element={<NotFound/>}/>
</Route></Routes>}

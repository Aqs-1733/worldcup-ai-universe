import axios from 'axios'
import type { ChatResponse, Match, NewsDetail, NewsItem, Player, Recommendation, Standing, Team, UserProfile, VisionResult } from '../types'

const apiBase = import.meta.env.VITE_API_BASE ?? '/api'
const assetBase = apiBase.startsWith('http') ? apiBase.replace(/\/api\/?$/, '') : ''

export const api = axios.create({ baseURL: apiBase, timeout: 60000 })
export const getTeams = async () => (await api.get<Team[]>('/teams')).data
export const getTeam = async (slug:string) => (await api.get<{team:Team;squad:Player[]}>(`/teams/${slug}`)).data
export const getPlayers = async (params:Record<string,unknown>={}) => (await api.get<Player[]>('/players',{params})).data
export const getPlayer = async (slug:string) => (await api.get<Player>(`/players/${slug}`)).data
export const refreshOfficialPlayers = async () => (await api.post('/players/refresh/official')).data
export const getMatches = async (force=false) => (await api.get<Match[]>('/worldcup/matches',{params:{force}})).data
export const getStandings = async () => (await api.get<Standing[]>('/worldcup/standings')).data
export const getBracket = async (force=false) => (await api.get<Array<{stage:string;matches:Match[]}>>('/worldcup/bracket',{params:{force}})).data
export const getHistory = async () => (await api.get<Array<Record<string,unknown>>>('/worldcup/history')).data
export const getOverview = async () => (await api.get<Record<string,string>>('/worldcup/overview')).data
export const getNews = async (q='', limit=80, category='') => (await api.get<NewsItem[]>('/news',{params:{q,limit,category}})).data
export const getNewsDetail = async (id:number) => (await api.get<NewsDetail>(`/news/${id}`)).data
export const refreshNews = async () => (await api.post('/news/refresh')).data
export const getUsers = async () => (await api.get<UserProfile[]>('/users')).data
export const loginUser = async (username:string,password:string) => (await api.post<UserProfile>('/users/login',{username,password})).data
export const registerUser = async (username:string,password:string) => (await api.post<UserProfile>('/users/register',{username,password})).data
export const saveUser = async (payload:Omit<UserProfile,'id'|'fan_persona'|'created_time'|'updated_time'>) => (await api.post<UserProfile>('/users',payload)).data
export const getDaily = async (id:number,force=false) => (await api.get<Recommendation[]>(`/users/${id}/daily`,{params:{force}})).data
export const sendChat = async (message:string,user_id?:number,session_id='web-session') => (await api.post<ChatResponse>('/chat',{message,user_id,session_id})).data
export const generateContent = async (payload:{topic:string;team:string;player:string;tone:string;generate_image?:boolean}) => (await api.post('/generation',payload)).data
export const analyzeVision = async (file:File,analysis_type:string) => { const form=new FormData();form.append('file',file);form.append('analysis_type',analysis_type);return (await api.post<VisionResult>('/vision/analyze',form)).data }
export const analyzeNews = async (payload:{title:string;content:string;source:string}) => (await api.post('/news/analyze',payload)).data
export const getHealth = async () => (await api.get('/health')).data
export const staticUrl = (path?:string|null) => {
  if (!path) return ''
  if (/^https?:\/\//.test(path)) return path
  return path.startsWith('/static') ? `${assetBase}${path}` : path
}

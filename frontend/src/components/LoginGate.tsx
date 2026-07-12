import { FormEvent, useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { LogIn, UserPlus } from 'lucide-react'
import { loginUser, registerUser } from '../lib/api'
import { setActiveUserId } from '../lib/session'

export default function LoginGate() {
  const qc = useQueryClient()
  const [mode, setMode] = useState<'login' | 'register'>('login')
  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')
  const [confirm, setConfirm] = useState('')
  const login = useMutation({
    mutationFn: () => loginUser(username.trim(), password),
    onSuccess: async (user) => {
      setActiveUserId(user.id)
      await qc.invalidateQueries({ queryKey: ['users'] })
    },
  })
  const register = useMutation({
    mutationFn: () => registerUser(username.trim(), password),
    onSuccess: async (user) => {
      setActiveUserId(user.id)
      await qc.invalidateQueries({ queryKey: ['users'] })
    },
  })

  const submit = (event: FormEvent) => {
    event.preventDefault()
    if (username.trim().length < 2 || password.length < 6) return
    if (mode === 'register') {
      if (password !== confirm || register.isPending) return
      register.mutate()
      return
    }
    if (!login.isPending) login.mutate()
  }

  const pending = login.isPending || register.isPending
  const canSubmit = username.trim().length >= 2 && password.length >= 6 && (mode === 'login' || password === confirm)

  return (
    <div className="fixed inset-0 z-[60] overflow-y-auto bg-black/75 p-4 backdrop-blur-sm">
      <form onSubmit={submit} className="mx-auto my-8 w-full max-w-md rounded-[30px] border border-white/10 bg-[#07110f] p-6 shadow-[0_30px_90px_rgba(0,0,0,.5)]">
        <div className="mb-5 grid h-14 w-14 place-items-center rounded-2xl bg-neon/10 text-neon">
          {mode === 'login' ? <LogIn size={24} /> : <UserPlus size={24} />}
        </div>
        <div className="eyebrow">{mode === 'login' ? 'LOGIN' : 'REGISTER'}</div>
        <h2 className="mt-2 text-2xl font-black">{mode === 'login' ? '账号密码登录' : '注册球迷账号'}</h2>
        <div className="mt-5 grid grid-cols-2 gap-2 rounded-2xl border border-white/10 bg-black/15 p-1">
          <button type="button" className={`rounded-xl px-4 py-2 text-sm font-bold ${mode === 'login' ? 'bg-neon text-pitch' : 'text-slate-400'}`} onClick={() => setMode('login')}>
            登录
          </button>
          <button type="button" className={`rounded-xl px-4 py-2 text-sm font-bold ${mode === 'register' ? 'bg-neon text-pitch' : 'text-slate-400'}`} onClick={() => setMode('register')}>
            注册
          </button>
        </div>
        <input
          className="input mt-5"
          value={username}
          onChange={(event) => setUsername(event.target.value)}
          placeholder="用户名"
          autoFocus
        />
        <input
          className="input mt-3"
          value={password}
          type="password"
          onChange={(event) => setPassword(event.target.value)}
          placeholder="密码，至少 6 位"
        />
        {mode === 'register' && (
          <input
            className="input mt-3"
            value={confirm}
            type="password"
            onChange={(event) => setConfirm(event.target.value)}
            placeholder="再次输入密码"
          />
        )}
        {mode === 'register' && confirm && password !== confirm && <div className="mt-3 text-sm text-amber-100">两次密码不一致。</div>}
        {login.isError && <div className="mt-3 text-sm text-red-200">登录失败：用户名或密码错误。</div>}
        {register.isError && <div className="mt-3 text-sm text-red-200">注册失败：用户名可能已被注册。</div>}
        <button className="btn-primary mt-5 w-full" disabled={!canSubmit || pending}>
          {pending ? '处理中' : mode === 'login' ? '登录' : '注册并进入'}
        </button>
      </form>
    </div>
  )
}

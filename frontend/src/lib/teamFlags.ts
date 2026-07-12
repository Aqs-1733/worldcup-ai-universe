import type { Team } from '../types'

const FALLBACK_FLAGS: Record<string, string> = {
  阿根廷: '🇦🇷',
  澳大利亚: '🇦🇺',
  奥地利: '🇦🇹',
  比利时: '🇧🇪',
  巴西: '🇧🇷',
  加拿大: '🇨🇦',
  智利: '🇨🇱',
  哥伦比亚: '🇨🇴',
  哥斯达黎加: '🇨🇷',
  克罗地亚: '🇭🇷',
  丹麦: '🇩🇰',
  英格兰: '🏴',
  厄瓜多尔: '🇪🇨',
  埃及: '🇪🇬',
  法国: '🇫🇷',
  德国: '🇩🇪',
  西德: '🇩🇪',
  加纳: '🇬🇭',
  意大利: '🇮🇹',
  日本: '🇯🇵',
  韩国: '🇰🇷',
  墨西哥: '🇲🇽',
  摩洛哥: '🇲🇦',
  荷兰: '🇳🇱',
  挪威: '🇳🇴',
  巴拉圭: '🇵🇾',
  葡萄牙: '🇵🇹',
  塞内加尔: '🇸🇳',
  西班牙: '🇪🇸',
  瑞典: '🇸🇪',
  瑞士: '🇨🇭',
  土耳其: '🇹🇷',
  乌拉圭: '🇺🇾',
  美国: '🇺🇸',
  苏联: '🇷🇺',
  捷克斯洛伐克: '🇨🇿',
  匈牙利: '🇭🇺',
}

const ISO3_TO_FLAG_IMAGE: Record<string, string> = {
  ALG: 'dz',
  ARG: 'ar',
  AUS: 'au',
  AUT: 'at',
  BEL: 'be',
  BIH: 'ba',
  BRA: 'br',
  CAN: 'ca',
  CIV: 'ci',
  COD: 'cd',
  COL: 'co',
  CPV: 'cv',
  CRO: 'hr',
  CUW: 'cw',
  CZE: 'cz',
  ECU: 'ec',
  EGY: 'eg',
  ENG: 'gb-eng',
  ESP: 'es',
  FRA: 'fr',
  GER: 'de',
  GHA: 'gh',
  HAI: 'ht',
  IRN: 'ir',
  IRQ: 'iq',
  JOR: 'jo',
  JPN: 'jp',
  KOR: 'kr',
  KSA: 'sa',
  MAR: 'ma',
  MEX: 'mx',
  NED: 'nl',
  NOR: 'no',
  NZL: 'nz',
  PAN: 'pa',
  PAR: 'py',
  POR: 'pt',
  QAT: 'qa',
  RSA: 'za',
  SCO: 'gb-sct',
  SEN: 'sn',
  SUI: 'ch',
  SWE: 'se',
  TUN: 'tn',
  TUR: 'tr',
  URU: 'uy',
  USA: 'us',
  UZB: 'uz',
}

export function flagForTeam(name: string, teams?: Team[]) {
  const normalized = name.trim()
  const match = teams?.find(
    (team) =>
      team.name === normalized ||
      team.english_name.toLowerCase() === normalized.toLowerCase(),
  )
  return match?.flag ?? FALLBACK_FLAGS[normalized] ?? ''
}

export function flagImageForTeam(name: string, teams?: Team[]) {
  const normalized = name.trim()
  const match = teams?.find(
    (team) =>
      team.name === normalized ||
      team.english_name.toLowerCase() === normalized.toLowerCase(),
  )
  const code = match ? ISO3_TO_FLAG_IMAGE[match.country_code] : undefined
  return code ? `https://flagcdn.com/w40/${code}.png` : ''
}

export function labelWithFlag(name: string, teams?: Team[]) {
  const flag = flagForTeam(name, teams)
  return flag ? `${flag} ${name}` : name
}

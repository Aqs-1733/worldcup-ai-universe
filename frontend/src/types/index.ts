export interface Team {
  id: number; slug: string; name: string; english_name: string; country_code: string; flag: string;
  confederation: string; world_rank: number; coach: string; stadium: string; titles: number;
  appearances: number; best_result: string; history: string; style: string; strengths: string[];
  weaknesses: string[]; form: string[]; win_probability: number; primary_color: string; secondary_color: string;
}
export interface Player {
  id: number; slug: string; name: string; english_name: string; team_id: number | null; country: string;
  flag: string; position: string; club: string; number: number | null; age: number | null; is_legend: boolean;
  career: string; world_cup_appearances: number; world_cup_goals: number; world_cup_assists: number;
  rating: number; form_score: number; strengths: string[]; weaknesses: string[]; impact: string; avatar_url: string;
}
export interface Match {
  id: number; stage: string; group_name: string; home_team: string; away_team: string; home_flag: string;
  away_flag: string; kickoff: string; venue: string; status: string; home_score: number | null;
  away_score: number | null; home_penalties: number | null; away_penalties: number | null;
  minute: number | null; stats: Record<string, unknown>;
}
export interface Standing {
  id: number; group_name: string; team: string; flag: string; played: number; won: number; drawn: number;
  lost: number; goals_for: number; goals_against: number; goal_difference: number; points: number;
}
export interface NewsItem {
  id: number; title: string; summary: string; source: string; source_url: string; image_url: string;
  published_at: string; category: string; categories: string[]; related_teams: string[]; related_players: string[];
  credibility_score: number; credibility_label: string; verification: Record<string, unknown>;
  original_title: string; original_summary: string; language: string;
}
export interface NewsDetail extends NewsItem {
  content: string; translated_title: string; translated_summary: string; translated_content: string;
}
export interface UserProfile {
  id: number; username: string; favorite_teams: string[]; favorite_players: string[]; dislike_teams: string[];
  favorite_content_type: string; fan_persona: string; onboarding_completed: boolean; created_time: string; updated_time: string;
}
export interface Recommendation {
  id: number; user_id: number; title: string; content: string; category: string; priority: number;
  related_entity: string; created_at: string; expires_at: string | null;
}
export interface ChatResponse {
  answer: string; agent: string; route_reason: string; sources: Array<{content:string;metadata:Record<string,unknown>;score:number}>;
  suggested_questions: string[]; model_mode: 'ark' | 'local'; metadata: Record<string, unknown>;
}
export interface VisionResult {
  analysis_type: string; detected_team: string | null; detected_flag: string | null; formation: string | null;
  confidence: number; observations: string[]; tactical_analysis: { strengths?: string[]; risks?: string[]; details?: Record<string,unknown>; [key:string]:unknown };
  model_mode: string; annotated_image_url: string | null;
}

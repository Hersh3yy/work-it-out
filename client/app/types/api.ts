// Shapes of what the API returns. Kept by hand until the OpenAPI contract (M10).

export interface SetRow {
  reps: number | null
  weight_kg: number | null
}

export interface Entry {
  id: string
  exercise_name: string
  sets: number | null
  reps: number | null
  weight_kg: number | null
  duration_seconds: number | null
  distance_meters: number | null
  volume_kg: number
  set_list: SetRow[]
}

export interface Limitation {
  id: number
  body_area: string | null
  area_label: string
  severity: string | null
  note: string
  started_on: string
  expected_until: string | null
  resolved_at: string | null
  active: boolean
  activity_log_id: string | null
}

export interface ActivityLog {
  id: string
  source: 'http' | 'telegram' | 'whatsapp' | 'simulate'
  log_type: 'workout' | 'biometrics' | 'meal' | 'general'
  message: string
  summary: string
  logged_on: string
  sent_at: string | null
  open_question: string | null
  entries: Entry[]
  limitation?: Limitation | null
}

export interface StrengthRecord {
  exercise: string
  max_weight_kg: number
  reps: number | null
  achieved_on: string
}

export interface Overview {
  user: {
    id: number
    name: string
    training_days_per_week: number | null
    primary_goal: string | null
    current_weight_kg: string | number | null
    weekly_adherence_rate: string | number | null
    current_streak_days: number | null
  }
  facts: {
    entered_by_user: { name: string; main_goal: string | null; training_days_per_week: number | null }
    this_week: { sessions: number; weekly_score_percent: number; streak_days: number; volume_kg: number }
    records: {
      strength: StrengthRecord[]
      endurance: {
        longest_distance: { exercise: string; distance_meters: number; achieved_on: string } | null
        best_pace: { exercise: string; seconds_per_km: number; pace_label: string; achieved_on: string } | null
      }
      sports: { name: string; sessions: number; longest_duration_minutes: number | null; last_played_on: string }[]
    }
    body_weight: {
      entries: number
      first: { date: string; kg: number } | null
      latest: { date: string; kg: number } | null
      change_kg: number | null
    }
    limitations: { area: string; note: string; severity: string | null; since: string; until: string | null }[]
    totals: { workout_sessions: number; logged_messages: number; days_since_last_session: number | null }
  }
  notes: { updated_at: string | null; kind: string | null; items: { text: string; cites: string[] }[] }
  limitations: Limitation[]
  recent_logs: ActivityLog[]
}

export interface PagedLogs {
  data: ActivityLog[]
  meta: { current_page: number; last_page: number; total: number }
}

// ============================================================
// 认证模块 API
// ============================================================
import http from './request'
import type { ApiResponse } from '@/shared/types'

export interface LoginParams {
  account: string
  password: string
  remember?: boolean
}

export interface LoginUserInfo {
  id: number
  account: string
  realname: string
  role_code: string
  role_name: string
}

export interface LoginResult {
  token: string
  token_expire_time: string
  user_info: LoginUserInfo
}

export function loginApi(params: LoginParams) {
  return http.post<ApiResponse<LoginResult>>('/auth/login', {
    account: params.account,
    password: params.password,
    remember: params.remember ? 1 : 0,
  })
}

export function logoutApi() {
  return http.post<ApiResponse<null>>('/auth/logout')
}

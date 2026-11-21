import { ElMessageBox } from 'element-plus'
import type { GatePrecheckViolation } from '@/types/gateControl'

let modalBusy = false

function violationHtml(items: GatePrecheckViolation[]) {
  return items
    .map((v) => `<p class="interlock-modal__item"><strong>${v.ruleName}</strong><span>${v.message}</span></p>`)
    .join('')
}

/** 存在阻断项且未开手动绕过：仅提示，不可继续 */
async function showBlocked(violations: GatePrecheckViolation[]) {
  const blocks = violations.filter((v) => v.severity === 'block')
  if (!blocks.length || modalBusy) return false

  modalBusy = true
  try {
    await ElMessageBox.alert(
      `<p class="interlock-modal__lead">以下规则未通过，请修改开度后重试（或开启顶部「手动绕过」强制执行）：</p>${violationHtml(blocks)}`,
      '无法执行',
      {
        type: 'error',
        confirmButtonText: '返回修改',
        center: true,
        dangerouslyUseHTMLString: true,
        closeOnClickModal: false,
        closeOnPressEscape: false,
        showClose: true,
        customClass: 'interlock-modal-box interlock-modal-box--block',
      },
    )
  } catch {
    /* 关闭 */
  } finally {
    modalBusy = false
  }
  return false
}

export type InterlockConfirmOptions = {
  title: string
  actionSummary: string
  confirmText?: string
  violations: GatePrecheckViolation[]
  /** 演示：允许在阻断时二次确认后强制执行 */
  allowForce?: boolean
}

/**
 * 执行/提交前统一确认：
 * - 有阻断且未开绕过 → 仅提示，返回 false
 * - 有阻断且开绕过 → 二次确认「强制执行」
 * - 有警告 → 同一弹窗内展示风险 + 操作摘要，确认后继续
 * - 无问题 → 普通确认
 */
export async function confirmInterlockAction(opts: InterlockConfirmOptions): Promise<boolean> {
  const { title, actionSummary, violations, allowForce = false } = opts
  const blocks = violations.filter((v) => v.severity === 'block')
  const warns = violations.filter((v) => v.severity === 'warn')

  if (blocks.length && !allowForce) {
    return showBlocked(violations)
  }

  const forceMode = blocks.length > 0 && allowForce
  const confirmText =
    opts.confirmText
    ?? (forceMode ? '强制执行' : warns.length ? '仍要继续' : '确认')

  let html = `<p class="interlock-confirm__action">${actionSummary}</p>`
  if (forceMode) {
    html += `<div class="interlock-confirm__warns">
      <p class="interlock-confirm__warn-title">互锁阻断（手动绕过已开，确认后仍将强制下发）</p>
      ${violationHtml(blocks)}
    </div>`
  }
  if (warns.length) {
    html += `<div class="interlock-confirm__warns">
      <p class="interlock-confirm__warn-title">风险提示（可继续，请谨慎操作）</p>
      ${violationHtml(warns)}
    </div>`
  }

  if (modalBusy) return false
  modalBusy = true
  try {
    await ElMessageBox.confirm(html, forceMode ? `${title}（强制）` : title, {
      type: forceMode || warns.length ? 'warning' : 'info',
      confirmButtonText: confirmText,
      cancelButtonText: '取消',
      center: true,
      dangerouslyUseHTMLString: true,
      closeOnClickModal: false,
      closeOnPressEscape: false,
      distinguishCancelAndClose: true,
      customClass:
        forceMode || warns.length
          ? 'interlock-modal-box interlock-confirm-box'
          : 'interlock-confirm-box interlock-confirm-box--plain',
    })
    return true
  } catch {
    return false
  } finally {
    modalBusy = false
  }
}

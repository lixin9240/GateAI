/**
 * 模糊查询 — 支持子串匹配、分词 AND、字符顺序匹配（如「高水」→「高水位」）
 */
export function fuzzyMatch(
  keyword: string,
  ...fields: (string | number | null | undefined)[]
): boolean {
  const kw = keyword.trim().toLowerCase()
  if (!kw) return true

  const haystack = fields
    .filter((v) => v !== null && v !== undefined && v !== '')
    .map(String)
    .join(' ')
    .toLowerCase()

  const tokens = kw.split(/\s+/).filter(Boolean)
  if (tokens.length > 1) {
    return tokens.every((t) => haystack.includes(t) || subsequenceMatch(haystack, t))
  }

  return haystack.includes(kw) || subsequenceMatch(haystack, kw)
}

function subsequenceMatch(text: string, pattern: string): boolean {
  let idx = 0
  for (const ch of pattern) {
    idx = text.indexOf(ch, idx)
    if (idx === -1) return false
    idx += 1
  }
  return true
}

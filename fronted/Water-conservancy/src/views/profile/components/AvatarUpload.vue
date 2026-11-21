<script setup lang="ts">
// ============================================================
// 头像上传组件 — 拖拽上传 + 预览
// ============================================================
import { ref, watch } from 'vue'
import { ElAvatar, ElUpload, ElProgress, ElMessage } from 'element-plus'
import type { UploadFile, UploadRawFile, UploadRequestOptions } from 'element-plus'
import { uploadAvatar } from '@/api/profile'
import { AVATAR_MAX_SIZE, AVATAR_ACCEPT } from '@/constants/profile'
import { fixAvatarUrl } from '@/utils'

// ── Props & Emits ──

const props = defineProps<{
  currentAvatar?: string
}>()

const emit = defineEmits<{
  'update:avatar': [url: string]
}>()

// ── 状态 ──

const previewUrl = ref<string>(props.currentAvatar || '')
const uploading = ref(false)
const uploadProgress = ref(0)

// ── 外部同步 ──

watch(
  () => props.currentAvatar,
  (val) => {
    if (!uploading.value) {
      previewUrl.value = fixAvatarUrl(val || '')
    }
  },
)

// ── 文件校验（集中校验，多处复用） ──

const VALID_TYPES = ['image/jpeg', 'image/png', 'image/jpg']

interface ValidationResult {
  valid: boolean
  message?: string
}

function validateFile(file: File): ValidationResult {
  if (file.size > AVATAR_MAX_SIZE) {
    const sizeMB = (file.size / 1024 / 1024).toFixed(1)
    return { valid: false, message: `文件大小 ${sizeMB}MB 超出限制，头像不能超过 2MB` }
  }
  if (!VALID_TYPES.includes(file.type)) {
    return { valid: false, message: '仅支持 JPG、JPEG、PNG 格式' }
  }
  return { valid: true }
}

// ── 方法 ──

/** 上传前校验（Element Plus before-upload 钩子） */
function beforeUpload(file: UploadRawFile) {
  const result = validateFile(file)
  if (!result.valid) {
    ElMessage.warning(result.message)
    return false
  }
  return true
}

/** 选取文件后创建本地预览（仅校验通过的文件） */
function onFileChange(file: UploadFile) {
  // beforeUpload 返回 false 时文件状态为 fail，不创建预览
  if (file.status === 'fail' || !file.raw) return

  // 冗余校验：防御 beforeUpload 在某些 Element Plus 版本中被绕过
  const result = validateFile(file.raw)
  if (!result.valid) {
    ElMessage.warning(result.message!)
    return
  }

  const reader = new FileReader()
  reader.onload = (e) => {
    previewUrl.value = (e.target?.result as string) || ''
  }
  reader.readAsDataURL(file.raw)
}

/** 自定义上传 — 后端优先，失败则本地 base64 兜底 */
async function customUpload(options: UploadRequestOptions) {
  const file = ((options.file as UploadFile).raw ?? options.file) as File

  const result = validateFile(file)
  if (!result.valid) {
    ElMessage.warning(result.message!)
    options.onError(new Error(result.message!))
    return
  }

  uploading.value = true
  uploadProgress.value = 30

  try {
    let avatarUrl = ''

    // 1) 优先走后端上传
    try {
      const res = await uploadAvatar(file)
      if (res.data?.code === 0 && res.data.data) {
        avatarUrl = res.data.data.avatar || res.data.data.url || (res.data.data as any).path || ''
        if (avatarUrl) {
          avatarUrl = fixAvatarUrl(avatarUrl)
          uploadProgress.value = 100
          previewUrl.value = avatarUrl
          emit('update:avatar', avatarUrl)
          options.onSuccess({ avatar: avatarUrl })
          ElMessage.success('头像已更新')
          return
        }
      }
    } catch {
      // 后端异常 → 降级 base64，不弹 toast
    }

    // 2) 后端失败 → 本地 base64 兜底
    const base64 = await new Promise<string>((resolve, reject) => {
      const reader = new FileReader()
      reader.onload = () => resolve(reader.result as string)
      reader.onerror = () => reject(new Error('文件读取失败'))
      reader.readAsDataURL(file)
    })

    avatarUrl = base64
    uploadProgress.value = 100
    previewUrl.value = avatarUrl
    emit('update:avatar', avatarUrl)
    options.onSuccess({ avatar: avatarUrl })
    ElMessage.success('头像已更新（本地）')
  } catch (err: unknown) {
    const msg = err && typeof err === 'object' && 'message' in err
      ? (err as { message: string }).message
      : '头像处理失败，请重试'
    ElMessage.error(msg)
    options.onError(new Error(msg))
  } finally {
    uploading.value = false
  }
}
</script>

<template>
  <div class="avatar-upload">
    <!-- 当前头像预览 -->
    <div class="avatar-upload__preview">
      <ElAvatar
        :size="80"
        :src="previewUrl"
        shape="circle"
        style="
          background: linear-gradient(135deg, #1890ff, #00d4ff);
          color: #fff;
          font-size: 32px;
          font-weight: 600;
        "
      >
        <span v-if="!previewUrl">?</span>
      </ElAvatar>
      <span class="avatar-upload__hint">预览</span>
    </div>

    <!-- 上传区域 -->
    <div class="avatar-upload__action">
      <ElUpload
        drag
        action="#"
        :accept="AVATAR_ACCEPT"
        :show-file-list="false"
        :before-upload="beforeUpload"
        :http-request="customUpload"
        :on-change="onFileChange"
        class="avatar-upload__dragger"
      >
        <div class="avatar-upload__drop-box">
          <span class="avatar-upload__icon">+</span>
          <span class="avatar-upload__text">拖拽或点击上传头像</span>
          <span class="avatar-upload__limit">支持 JPG/PNG, 不超过 2MB</span>
        </div>
      </ElUpload>

      <!-- 上传进度 -->
      <ElProgress
        v-if="uploading"
        :percentage="uploadProgress"
        :stroke-width="6"
        style="margin-top: 8px"
      />
    </div>
  </div>
</template>

<style scoped lang="scss">
.avatar-upload {
  display: flex;
  align-items: center;
  gap: var(--spacing-lg);

  &__preview {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: var(--spacing-xs);
    flex-shrink: 0;
  }

  &__hint {
    font-size: var(--font-size-xs);
    color: var(--color-text-secondary);
  }

  &__action {
    flex: 1;
  }

  &__dragger {
    :deep(.el-upload-dragger) {
      padding: 20px;
      border-radius: 8px;
    }
  }

  &__drop-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
  }

  &__icon {
    font-size: 28px;
    color: var(--color-text-secondary);
  }

  &__text {
    font-size: 14px;
    color: var(--color-text);
  }

  &__limit {
    font-size: 12px;
    color: var(--color-text-secondary);
  }
}
</style>

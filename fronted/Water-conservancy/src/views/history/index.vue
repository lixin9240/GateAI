<template>
  <div class="history-shell">
    <router-view />
  </div>
</template>

<style scoped lang="scss">
.history-shell {
  display: flex;
  flex-direction: column;
  height: calc(100vh - 56px);
  overflow: hidden;

  > :deep(*) {
    flex: 1;
    min-height: 0;
  }
}
</style>

<style lang="scss">
.history-shell > * {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
}
</style>

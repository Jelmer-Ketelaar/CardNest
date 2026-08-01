<template>
  <main class="auth-page">
    <section class="panel" aria-labelledby="health-heading">
      <p class="eyebrow">Status</p>
      <h1 id="health-heading">Frontend beschikbaar</h1>
      <p role="status">De CardNest-frontend draait.</p>
      <RouterLink class="button button--primary inline-button" :to="{ name: 'marketplace' }">
        Naar de marktplaats
      </RouterLink>
    </section>
  </main>
</template>

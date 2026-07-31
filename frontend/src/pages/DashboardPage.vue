<template>
  <main class="page-container">
    <section class="panel panel--wide dashboard-hero">
      <p class="eyebrow">Dashboard</p>
      <h1>Je verzameling begint hier.</h1>
      <p>Registreer ieder fysiek exemplaar afzonderlijk en zie direct welke kaarten je dubbel hebt.</p>
      <RouterLink class="button button--primary inline-button" :to="{ name: 'collection' }">
        Open mijn collectie
      </RouterLink>
    </section>
  </main>
</template>

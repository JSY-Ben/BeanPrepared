<?php

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>BeanPrepared - Home</title>
  <link rel="icon" href="/Web/beanprepared/public/icon-512x512.png" sizes="512x512" type="image/png">
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
    integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
    crossorigin="anonymous"
  >
  <link rel="stylesheet" href="/Web/beanprepared/public/css/site.css">
</head>
<body>
  <nav class="navbar navbar-expand-lg bg-body-tertiary border-bottom">
    <div class="container">
      <a class="navbar-brand fw-semibold d-flex align-items-center gap-2" href="home.php">
        <img src="/Web/beanprepared/public/icon.png" alt="BeanPrepared" class="site-logo">
        <span>BeanPrepared</span>
      </a>
      <div class="navbar-nav">
        <a class="nav-link active" href="home.php">Home</a>
        <a class="nav-link" href="index.php">Upcoming Events</a>
        <a class="nav-link" href="submit.php">Submit Event</a>
      </div>
    </div>
  </nav>

  <main class="container py-4">
    <div class="row align-items-center g-4">
      <div class="col-12 col-lg-6">
        <div class="section-card section-emphasis">
          <h1 class="mb-2">Welcome to BeanPrepared</h1>
          <p class="brand-subtitle mb-3">
            BeanPrepared keeps your community connected with upcoming events and timely updates.
            Discover what’s coming up and get notified on the go.
          </p>
          <div class="d-flex flex-wrap gap-3">
            <a class="btn btn-warning" href="https://example.com/ios" target="_blank" rel="noreferrer">Download on iOS</a>
            <a class="btn btn-outline-warning" href="https://example.com/android" target="_blank" rel="noreferrer">Get it on Android</a>
          </div>
        </div>
      </div>
      <div class="col-12 col-lg-6">
        <div class="card shadow-sm event-card event-card-compact">
          <div class="card-body">
            <div class="event-meta">Stay in the loop</div>
            <h2 class="event-title">Your events, beautifully organized</h2>
            <p class="event-description mb-0">
              Browse the upcoming calendar, filter by event types, and submit new requests in seconds.
              BeanPrepared makes it easy to plan ahead.
            </p>
          </div>
        </div>
      </div>
    </div>
  </main>
</body>
</html>

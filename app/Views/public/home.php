<?php

declare(strict_types=1);

use Cva\Support\Html;
use Cva\Support\Url;

// This first public page uses only confirmed identity and project scope.
// Academy-approved content is escaped before display; rich HTML is not accepted.
$pageTitle = $appConfig['name'] . ' | Volleyball academy';
$introduction = trim((string) ($siteContent['introduction'] ?? ''));
$aboutText = trim((string) ($siteContent['about_text'] ?? ''));
$mission = trim((string) ($siteContent['mission'] ?? ''));
$vision = trim((string) ($siteContent['vision'] ?? ''));
$coachingPhilosophy = trim((string) ($siteContent['coaching_philosophy'] ?? ''));
$contactEmail = trim((string) ($siteContent['contact_email'] ?? ''));
$contactPhone = trim((string) ($siteContent['contact_phone'] ?? ''));
$contactLocation = trim((string) ($siteContent['contact_location'] ?? ''));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Information about Central Volleyball Academy programs, coaches, events, and registration.">
    <title><?= Html::escape($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= Html::escape(Url::path('/assets/css/site.css')) ?>">
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to content</a>

    <header class="site-header">
        <div class="container header-content">
            <a class="wordmark" href="<?= Html::escape(Url::path('/')) ?>" aria-label="Central Volleyball Academy home">
                <?= Html::escape($appConfig['name']) ?>
            </a>

            <!-- The details menu provides a keyboard-friendly small-screen nav without JavaScript. -->
            <details class="mobile-menu">
                <summary>Menu</summary>
                <nav class="mobile-nav" aria-label="Mobile navigation">
                    <a href="#about">About</a>
                    <a href="#programs">Programs</a>
                    <a href="#coaches">Coaches</a>
                    <a href="#events">Events</a>
                    <a href="#gallery">Gallery</a>
                    <a href="#contact">Contact</a>
                </nav>
            </details>

            <nav class="desktop-nav" aria-label="Main navigation">
                <a href="#about">About</a>
                <a href="#programs">Programs</a>
                <a href="#coaches">Coaches</a>
                <a href="#events">Events</a>
                <a href="#gallery">Gallery</a>
                <a href="#contact">Contact</a>
            </nav>

            <a class="button button-small" href="<?= Html::escape(Url::path('/registration')) ?>">Register</a>
        </div>
    </header>

    <main id="main-content">
        <section class="hero container" aria-labelledby="hero-title">
            <div class="hero-copy">
                <p class="eyebrow">Central Volleyball Academy</p>
                <h1 id="hero-title">Volleyball starts with a team.</h1>
                <p class="hero-intro">
                    <?= Html::escape($introduction !== '' ? $introduction : 'Learn about the academy, meet the team, and send a player registration application.') ?>
                </p>
                <div class="hero-actions">
                    <a class="button" href="<?= Html::escape(Url::path('/registration')) ?>">Register a player</a>
                    <a class="text-link" href="#about">About the academy</a>
                </div>
            </div>
            <aside class="hero-note" aria-label="Information status">
                <p class="hero-note-label">Academy information</p>
                    <p>Academy staff can update public information and review player applications through the admin area.</p>
            </aside>
        </section>

        <section class="section-band" id="about" aria-labelledby="about-title">
            <div class="container section-layout">
                <p class="section-index">01 / About</p>
                <div class="section-copy">
                    <h2 id="about-title">About the academy</h2>
                    <p class="preserve-lines"><?= Html::escape($aboutText !== '' ? $aboutText : 'Academy history and team information will appear here when confirmed by the academy.') ?></p>
                    <?php if ($mission !== ''): ?><h3>Our mission</h3><p class="preserve-lines"><?= Html::escape($mission) ?></p><?php endif; ?>
                    <?php if ($vision !== ''): ?><h3>Our vision</h3><p class="preserve-lines"><?= Html::escape($vision) ?></p><?php endif; ?>
                    <?php if ($coachingPhilosophy !== ''): ?><h3>Coaching philosophy</h3><p class="preserve-lines"><?= Html::escape($coachingPhilosophy) ?></p><?php endif; ?>
                </div>
            </div>
        </section>

        <section class="container content-section" id="programs" aria-labelledby="programs-title">
            <div class="section-layout">
                <p class="section-index">02 / Programs</p>
                <div class="section-copy">
                    <h2 id="programs-title">Training programs</h2>
                    <?php if ($programs === []): ?>
                        <p>Academy-confirmed program information will appear here when staff add and publish it.</p>
                    <?php else: ?>
                        <div class="program-grid">
                            <?php foreach ($programs as $program): ?>
                                <?php $programId = (int) $program['id']; ?>
                                <article class="card program-card">
                                    <p class="eyebrow"><?= Html::escape($program['category']) ?></p>
                                    <h3><?= Html::escape($program['title']) ?></h3>
                                    <?php if (is_string($program['age_group']) && $program['age_group'] !== ''): ?><p><strong>Age group:</strong> <?= Html::escape($program['age_group']) ?></p><?php endif; ?>
                                    <p class="preserve-lines"><?= Html::escape($program['description']) ?></p>
                                    <?php if (!empty($programSchedules[$programId])): ?>
                                        <h4>Schedule</h4>
                                        <ul class="program-details-list"><?php foreach ($programSchedules[$programId] as $schedule): ?><li><?= Html::escape($schedule) ?></li><?php endforeach; ?></ul>
                                    <?php endif; ?>
                                    <?php if (!empty($programCoaches[$programId])): ?>
                                        <h4>Coaching staff</h4>
                                        <ul class="program-details-list"><?php foreach ($programCoaches[$programId] as $coach): ?><li><?= Html::escape($coach['first_name'] . ' ' . $coach['last_name'] . ' — ' . $coach['role_title']) ?></li><?php endforeach; ?></ul>
                                    <?php endif; ?>
                                    <?php if (is_string($program['fee_details']) && $program['fee_details'] !== ''): ?><p><strong>Fees:</strong> <?= Html::escape($program['fee_details']) ?></p><?php endif; ?>
                                    <?php if ($program['capacity'] !== null): ?><p><strong>Capacity:</strong> <?= (int) $program['capacity'] ?></p><?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="section-band" id="coaches" aria-labelledby="coaches-title">
            <div class="container section-layout">
                <p class="section-index">03 / Coaches</p>
                <div class="section-copy">
                    <h2 id="coaches-title">Coaching staff</h2>
                    <?php if ($coaches === []): ?>
                        <p>Academy-approved coach profiles will appear here when staff add and publish them.</p>
                    <?php else: ?>
                        <div class="coach-grid">
                            <?php foreach ($coaches as $coach): ?>
                                <article class="card coach-card">
                                    <?php if ($coach['photo_id'] !== null): ?>
                                        <img class="coach-photo" src="<?= Html::escape($mediaBasePath . '?id=' . (int) $coach['photo_id']) ?>" alt="<?= Html::escape((string) ($coach['photo_alt'] ?? ('Photograph of ' . $coach['first_name'] . ' ' . $coach['last_name']))) ?>" loading="lazy">
                                    <?php endif; ?>
                                    <h3><?= Html::escape($coach['first_name'] . ' ' . $coach['last_name']) ?></h3>
                                    <p class="coach-role"><?= Html::escape($coach['role_title']) ?></p>
                                    <p class="preserve-lines"><?= Html::escape($coach['biography']) ?></p>
                                    <?php if (is_string($coach['experience'] ?? null) && trim($coach['experience']) !== ''): ?>
                                        <h4>Experience</h4>
                                        <p class="preserve-lines"><?= Html::escape($coach['experience']) ?></p>
                                    <?php endif; ?>
                                    <?php if (is_string($coach['qualifications']) && trim($coach['qualifications']) !== ''): ?>
                                        <h4>Qualifications</h4>
                                        <p class="preserve-lines"><?= Html::escape($coach['qualifications']) ?></p>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="container content-section" id="events" aria-labelledby="events-title">
            <div class="section-layout">
                <p class="section-index">04 / Events</p>
                <div class="section-copy">
                    <h2 id="events-title">Events and activities</h2>
                    <?php if ($events === []): ?>
                        <p>Upcoming events will appear here when the academy adds and publishes confirmed activities.</p>
                    <?php else: ?>
                        <p class="field-help">Event times are shown in the academy's local time.</p>
                        <div class="event-grid">
                            <?php foreach ($events as $event): ?>
                                <?php
                                $eventStartsAt = new DateTimeImmutable($event['starts_at']);
                                $eventEndsAt = new DateTimeImmutable($event['ends_at']);
                                $registrationLabels = ['open' => 'Registration open', 'closed' => 'Registration closed', 'not_required' => 'Registration not required'];
                                ?>
                                <article class="card event-card<?= $event['status'] === 'cancelled' ? ' event-card-cancelled' : '' ?>">
                                    <?php if ($event['photo_id'] !== null): ?><img class="event-photo" src="<?= Html::escape($mediaBasePath . '?id=' . (int) $event['photo_id']) ?>" alt="<?= Html::escape((string) ($event['photo_alt'] ?? $event['title'])) ?>" loading="lazy"><?php endif; ?>
                                    <?php if ($event['status'] === 'cancelled'): ?><p class="status-label status-label-muted">Cancelled</p><?php endif; ?>
                                    <h3><?= Html::escape($event['title']) ?></h3>
                                    <p class="preserve-lines"><?= Html::escape($event['description']) ?></p>
                                    <p><strong>Date:</strong> <time datetime="<?= Html::escape(str_replace(' ', 'T', $event['starts_at'])) ?>"><?= Html::escape($eventStartsAt->format('l, F j, Y')) ?></time></p>
                                    <p><strong>Time:</strong> <?= Html::escape($eventStartsAt->format('g:i A')) ?>–<?= Html::escape($eventEndsAt->format('g:i A')) ?></p>
                                    <p><strong>Location:</strong> <?= Html::escape($event['location']) ?></p>
                                    <p><strong><?= Html::escape($registrationLabels[$event['registration_status']] ?? 'Registration details') ?></strong></p>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="container content-section" id="gallery" aria-labelledby="gallery-title">
            <div class="section-layout">
                <p class="section-index">05 / Gallery</p>
                <div class="section-copy">
                    <h2 id="gallery-title">Academy gallery</h2>
                    <?php if ($galleryItems === []): ?>
                        <p>Photos and videos approved by the academy will appear here.</p>
                    <?php else: ?>
                        <div class="gallery-grid">
                            <?php foreach ($galleryItems as $item): ?>
                                <?php $mediaUrl = $mediaBasePath . '?id=' . (int) $item['id']; ?>
                                <figure class="gallery-card">
                                    <?php if ($item['media_kind'] === 'image'): ?>
                                        <img src="<?= Html::escape($mediaUrl) ?>" alt="<?= Html::escape($item['alternative_text']) ?>" loading="lazy">
                                    <?php else: ?>
                                        <video controls preload="metadata" aria-label="<?= Html::escape($item['alternative_text']) ?>"><source src="<?= Html::escape($mediaUrl) ?>" type="<?= Html::escape($item['mime_type']) ?>">Your browser cannot play this video.</video>
                                    <?php endif; ?>
                                    <figcaption><strong><?= Html::escape($item['title']) ?></strong><?php if (is_string($item['caption']) && $item['caption'] !== ''): ?><span class="preserve-lines"><?= Html::escape($item['caption']) ?></span><?php endif; ?></figcaption>
                                </figure>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="section-band" id="registration" aria-labelledby="registration-title">
            <div class="container section-layout">
                <p class="section-index">06 / Registration</p>
                <div class="section-copy">
                    <h2 id="registration-title">Join the team</h2>
                    <p>Send a player application to the academy. Staff will review the details and contact the applicant or guardian.</p>
                    <a class="button" href="<?= Html::escape(Url::path('/registration')) ?>">Start registration</a>
                </div>
            </div>
        </section>

        <section class="container contact-section" id="contact" aria-labelledby="contact-title">
            <div class="section-layout">
                <p class="section-index">07 / Contact</p>
                <div class="section-copy">
                    <h2 id="contact-title">Contact the academy</h2>
                    <?php if ($contactEmail !== ''): ?><p>Email: <a href="mailto:<?= Html::escape($contactEmail) ?>"><?= Html::escape($contactEmail) ?></a></p><?php endif; ?>
                    <?php if ($contactPhone !== ''): ?><p>Phone: <a href="tel:<?= Html::escape(preg_replace('/[^0-9+]/', '', $contactPhone)) ?>"><?= Html::escape($contactPhone) ?></a></p><?php endif; ?>
                    <?php if ($contactLocation !== ''): ?><p><?= Html::escape($contactLocation) ?></p><?php endif; ?>
                    <?php if ($contactEmail === '' && $contactPhone === '' && $contactLocation === ''): ?><p>Academy contact details will appear here when confirmed.</p><?php endif; ?>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container footer-content">
            <p><?= Html::escape($appConfig['name']) ?></p>
            <div class="footer-links">
                <a href="#main-content">Back to top</a>
                <a href="<?= Html::escape(Url::path('/admin/login')) ?>">Staff sign in</a>
            </div>
        </div>
    </footer>
</body>
</html>

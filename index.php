<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kirki eCommerce — Downloads</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-950 text-white antialiased">
    <div class="pointer-events-none fixed inset-0 overflow-hidden" aria-hidden="true">
        <div class="absolute -left-1/4 top-0 h-[500px] w-[500px] rounded-full bg-violet-600/10 blur-3xl"></div>
        <div class="absolute -right-1/4 bottom-0 h-[500px] w-[500px] rounded-full bg-blue-600/10 blur-3xl"></div>
    </div>

    <main class="relative flex min-h-screen items-center justify-center px-4 py-16">
        <div class="w-full max-w-3xl">
            <header class="mb-10 text-center">
                <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">Kirki eCommerce</h1>
                <p class="mt-2 text-slate-400">Download Statistics</p>
            </header>

            <div id="error-banner" class="mb-6 hidden rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-center text-sm text-red-300" role="alert">
                Unable to fetch download data. Retrying automatically…
            </div>

            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <article id="total-card" class="rounded-2xl border border-slate-800 bg-slate-900/80 p-8 shadow-xl backdrop-blur transition-colors duration-300">
                    <p class="text-sm uppercase tracking-wider text-slate-400">Total Downloads</p>
                    <p id="total-count" class="mt-3 text-5xl font-bold tabular-nums tracking-tight text-white" aria-live="polite">—</p>
                    <p class="mt-2 text-sm text-slate-500">All releases</p>
                </article>

                <article id="latest-card" class="rounded-2xl border border-slate-800 bg-slate-900/80 p-8 shadow-xl backdrop-blur transition-colors duration-300">
                    <p class="text-sm uppercase tracking-wider text-slate-400">Latest Release</p>
                    <p id="latest-count" class="mt-3 text-5xl font-bold tabular-nums tracking-tight text-white" aria-live="polite">—</p>
                    <div id="latest-meta" class="mt-4 space-y-2">
                        <span id="latest-tag" class="inline-block rounded-full bg-violet-500/20 px-3 py-1 font-mono text-sm text-violet-300">—</span>
                        <p id="latest-name" class="text-sm text-slate-300">—</p>
                        <p id="latest-date" class="text-sm text-slate-500">—</p>
                    </div>
                </article>
            </div>

            <footer class="mt-10 flex items-center justify-center gap-2 text-sm text-slate-500">
                <span class="relative flex h-2.5 w-2.5" aria-hidden="true">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                </span>
                <span>Live · <span id="last-updated">Connecting…</span></span>
            </footer>
        </div>
    </main>

    <script>
        const API_URL = "https://api.github.com/repos/themeum/kirki-ecommerce/releases";
        const POLL_INTERVAL = 60_000;

        const totalCountEl = document.getElementById("total-count");
        const latestCountEl = document.getElementById("latest-count");
        const latestTagEl = document.getElementById("latest-tag");
        const latestNameEl = document.getElementById("latest-name");
        const latestDateEl = document.getElementById("latest-date");
        const lastUpdatedEl = document.getElementById("last-updated");
        const errorBannerEl = document.getElementById("error-banner");
        const totalCardEl = document.getElementById("total-card");
        const latestCardEl = document.getElementById("latest-card");

        let lastUpdatedAt = null;
        let currentTotal = 0;
        let currentLatest = 0;
        let isFirstLoad = true;

        const sumAssetDownloads = (release) =>
            (release.assets ?? []).reduce((sum, asset) => sum + asset.download_count, 0);

        const formatDate = (iso) => {
            if (!iso) return "—";
            return new Date(iso).toLocaleDateString("en-US", {
                year: "numeric",
                month: "short",
                day: "numeric",
            });
        };

        const formatNumber = (value) =>
            Math.round(value).toLocaleString("en-US");

        const animateCount = (element, from, to, duration = 800) => {
            if (from === to) {
                element.textContent = formatNumber(to);
                return;
            }

            const start = performance.now();

            const step = (now) => {
                const elapsed = now - start;
                const progress = Math.min(elapsed / duration, 1);
                const eased = 1 - Math.pow(1 - progress, 3);
                const current = from + (to - from) * eased;

                element.textContent = formatNumber(current);

                if (progress < 1) {
                    requestAnimationFrame(step);
                } else {
                    element.textContent = formatNumber(to);
                }
            };

            requestAnimationFrame(step);
        };

        const setErrorState = (hasError) => {
            errorBannerEl.classList.toggle("hidden", !hasError);
            totalCardEl.classList.toggle("border-red-500/30", hasError);
            latestCardEl.classList.toggle("border-red-500/30", hasError);
        };

        const updateRelativeTime = () => {
            if (!lastUpdatedAt) {
                lastUpdatedEl.textContent = "Connecting…";
                return;
            }

            const seconds = Math.floor((Date.now() - lastUpdatedAt) / 1000);

            if (seconds < 5) {
                lastUpdatedEl.textContent = "Updated just now";
                return;
            }

            if (seconds < 60) {
                lastUpdatedEl.textContent = `Updated ${seconds}s ago`;
                return;
            }

            const minutes = Math.floor(seconds / 60);
            lastUpdatedEl.textContent = `Updated ${minutes}m ago`;
        };

        const fetchAndRender = async () => {
            try {
                const response = await fetch(API_URL);

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const releases = await response.json();

                if (!Array.isArray(releases)) {
                    throw new Error("Invalid response");
                }

                const totalDownloads = releases.reduce(
                    (sum, release) => sum + sumAssetDownloads(release),
                    0
                );

                const latestRelease = releases[0];
                const latestDownloads = latestRelease ? sumAssetDownloads(latestRelease) : 0;

                const totalFrom = isFirstLoad ? 0 : currentTotal;
                const latestFrom = isFirstLoad ? 0 : currentLatest;

                animateCount(totalCountEl, totalFrom, totalDownloads);
                animateCount(latestCountEl, latestFrom, latestDownloads);

                currentTotal = totalDownloads;
                currentLatest = latestDownloads;
                isFirstLoad = false;

                if (latestRelease) {
                    latestTagEl.textContent = latestRelease.tag_name;
                    latestNameEl.textContent = latestRelease.name || latestRelease.tag_name;
                    latestDateEl.textContent = formatDate(latestRelease.published_at);
                } else {
                    latestTagEl.textContent = "No releases";
                    latestNameEl.textContent = "No releases yet";
                    latestDateEl.textContent = "—";
                }

                lastUpdatedAt = Date.now();
                updateRelativeTime();
                setErrorState(false);
            } catch {
                setErrorState(true);

                if (isFirstLoad) {
                    totalCountEl.textContent = "—";
                    latestCountEl.textContent = "—";
                }
            }
        };

        window.addEventListener("DOMContentLoaded", () => {
            fetchAndRender();
            setInterval(fetchAndRender, POLL_INTERVAL);
            setInterval(updateRelativeTime, 1000);
        });
    </script>
</body>
</html>

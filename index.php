<?php
// ============================================================
//  SERVER-SIDE STATE + PUSHER TRIGGER (single-file backend)
// ============================================================

// Simple file-based "database" so all devices share one queue.
$STATE_FILE = __DIR__ . '/karaoke_state.json';

function readState() {
    global $STATE_FILE;
    if (!file_exists($STATE_FILE)) {
        return ['queue' => [], 'current' => null, 'version' => 0];
    }
    $raw = @file_get_contents($STATE_FILE);
    $data = $raw ? json_decode($raw, true) : null;
    if (!is_array($data)) return ['queue' => [], 'current' => null, 'version' => 0];
    if (!isset($data['queue']))   $data['queue'] = [];
    if (!isset($data['current'])) $data['current'] = null;
    if (!isset($data['version'])) $data['version'] = 0;
    return $data;
}

function writeState($state) {
    global $STATE_FILE;
    $state['version'] = (isset($state['version']) ? $state['version'] : 0) + 1;
    // Atomic write
    $tmp = $STATE_FILE . '.tmp';
    file_put_contents($tmp, json_encode($state, JSON_UNESCAPED_UNICODE), LOCK_EX);
    rename($tmp, $STATE_FILE);
    return $state;
}

function jsonOut($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode($data);
    exit;
}

// ------------------------------------------------------------
//  PUSHER CREDENTIALS
// ------------------------------------------------------------
$PUSHER_APP_ID  = '2165316';
$PUSHER_KEY     = '7580414d81220f9bb60b';
$PUSHER_SECRET  = 'd4f3a0189cae5d758df3';   // <-- ROTATE IN PUSHER DASHBOARD
$PUSHER_CLUSTER = 'ap1';
$PUSHER_CHANNEL = 'karaoke-public-room';
$PUSHER_EVENT   = 'queue-update';

function pusherTrigger($type, $payload = []) {
    global $PUSHER_APP_ID, $PUSHER_KEY, $PUSHER_SECRET, $PUSHER_CLUSTER, $PUSHER_CHANNEL, $PUSHER_EVENT;
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($autoload)) {
        error_log('[karaoke] Pusher SDK not installed. Run: composer require pusher/pusher-php-server');
        return false;
    }
    require_once $autoload;
    try {
        $pusher = new Pusher\Pusher(
            $PUSHER_KEY,
            $PUSHER_SECRET,
            $PUSHER_APP_ID,
            ['cluster' => $PUSHER_CLUSTER, 'useTLS' => true]
        );
        $pusher->trigger($PUSHER_CHANNEL, $PUSHER_EVENT, [
            'type' => $type,
            'payload' => $payload,
            'ts' => round(microtime(true) * 1000)
        ]);
        return true;
    } catch (Exception $e) {
        error_log('[karaoke] Pusher trigger failed: ' . $e->getMessage());
        return false;
    }
}

// ------------------------------------------------------------
//  API ROUTES
// ------------------------------------------------------------
$action = $_GET['action'] ?? null;
$method = $_SERVER['REQUEST_METHOD'];

if ($action) {
    header('Content-Type: application/json');
    header('Cache-Control: no-store');

    // GET state — used on page load and after every remote update
    if ($action === 'state' && $method === 'GET') {
        jsonOut(readState());
    }

    // POST queue — full replacement (client is source of truth for the change, server persists it)
    if ($action === 'queue' && $method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true);
        if (!is_array($body) || !isset($body['queue']) || !is_array($body['queue'])) {
            jsonOut(['error' => 'Invalid queue payload'], 400);
        }
        $state = readState();
        $state['queue'] = $body['queue'];
        if (array_key_exists('current', $body)) {
            $state['current'] = $body['current'];
        }
        $state = writeState($state);
        // Broadcast to every other device
        pusherTrigger('sync', ['version' => $state['version']]);
        jsonOut($state);
    }

    // POST a single "add to queue" — safer against races
    if ($action === 'add' && $method === 'POST') {
        $song = json_decode(file_get_contents('php://input'), true);
        if (!is_array($song) || empty($song['videoId'])) {
            jsonOut(['error' => 'Invalid song'], 400);
        }
        $state = readState();
        foreach ($state['queue'] as $q) {
            if ($q['videoId'] === $song['videoId']) {
                jsonOut(['ok' => false, 'reason' => 'already_queued', 'state' => $state]);
            }
        }
        $state['queue'][] = $song;
        $state = writeState($state);
        pusherTrigger('sync', ['version' => $state['version']]);
        jsonOut(['ok' => true, 'state' => $state]);
    }

    // POST remove a song
    if ($action === 'remove' && $method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true);
        $vid = $body['videoId'] ?? null;
        if (!$vid) jsonOut(['error' => 'Missing videoId'], 400);
        $state = readState();
        $state['queue'] = array_values(array_filter($state['queue'], fn($s) => $s['videoId'] !== $vid));
        $state = writeState($state);
        pusherTrigger('sync', ['version' => $state['version']]);
        jsonOut($state);
    }

    // POST clear
    if ($action === 'clear' && $method === 'POST') {
        $state = readState();
        $state['queue'] = [];
        $state = writeState($state);
        pusherTrigger('sync', ['version' => $state['version']]);
        jsonOut($state);
    }

    // POST current (now playing)
    if ($action === 'current' && $method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true);
        $state = readState();
        $state['current'] = $body['current'] ?? null;
        $state = writeState($state);
        pusherTrigger('current', ['current' => $state['current']]);
        jsonOut($state);
    }

    // POST play-next: shift queue, update current, broadcast
    if ($action === 'next' && $method === 'POST') {
        $state = readState();
        if (count($state['queue']) === 0) {
            $state['current'] = null;
            $state = writeState($state);
            pusherTrigger('sync', ['version' => $state['version']]);
            jsonOut($state);
        }
        $next = array_shift($state['queue']);
        $state['current'] = $next;
        $state = writeState($state);
        pusherTrigger('next', ['current' => $next, 'version' => $state['version']]);
        jsonOut($state);
    }

    jsonOut(['error' => 'Unknown action'], 404);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
  <title>Karaoke / Videoke · YouTube</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    :root {
      --bg: #0b0b0f;
      --card-bg: #14141c;
      --card-hover: #1c1c26;
      --primary: #8b5cf6;
      --primary-glow: #7c3aed;
      --text: #f0f0f5;
      --text-muted: #9ca3af;
      --border: #2a2a36;
      --radius: 1.25rem;
      --radius-sm: 0.9rem;
      --transition: all 0.2s ease;
    }

    body {
      font-family: 'Inter', sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      padding: 1rem;
      line-height: 1.5;
    }

    @media (min-width: 1800px) {
      body { font-size: 1.3rem; padding: 2rem; }
      .search-input, .search-btn { font-size: 1.3rem; padding: 1rem 1.8rem; }
      .song-card { padding: 1.5rem; }
      .song-title { font-size: 1.3rem; }
    }

    .app-container { max-width: 1600px; margin: 0 auto; }

    .app-header {
      display: flex; align-items: center; justify-content: space-between;
      margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;
    }

    .logo {
      display: flex; align-items: center; gap: 0.75rem;
      font-weight: 700; font-size: 2rem; letter-spacing: -0.02em;
      flex-wrap: wrap;
    }
    .logo i { color: var(--primary); font-size: 2.2rem; }

    .nav-links {
      display: flex; gap: 0.75rem; background: var(--card-bg);
      padding: 0.5rem; border-radius: 4rem; border: 1px solid var(--border);
    }

    .nav-btn {
      background: transparent; border: none; color: var(--text-muted);
      font-weight: 600; padding: 0.6rem 1.5rem; border-radius: 2rem;
      cursor: pointer; transition: var(--transition); font-size: 1rem;
      display: flex; align-items: center; gap: 0.5rem;
    }
    .nav-btn.active {
      background: var(--primary); color: white;
      box-shadow: 0 4px 14px rgba(139, 92, 246, 0.4);
    }
    .nav-btn:hover:not(.active) { color: var(--text); background: rgba(255, 255, 255, 0.05); }

    .search-section { margin-bottom: 2.5rem; }
    .search-bar { display: flex; gap: 0.75rem; margin-bottom: 1.5rem; }

    .search-input {
      flex: 1; background: var(--card-bg); border: 1px solid var(--border);
      border-radius: 4rem; padding: 1rem 1.8rem; font-size: 1.1rem;
      color: var(--text); outline: none; transition: var(--transition);
    }
    .search-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.3); }
    .search-input::placeholder { color: var(--text-muted); }

    .search-btn {
      background: var(--primary); border: none; border-radius: 4rem;
      padding: 1rem 2rem; font-weight: 600; font-size: 1.1rem; color: white;
      cursor: pointer; transition: var(--transition);
      display: flex; align-items: center; gap: 0.6rem; white-space: nowrap;
      box-shadow: 0 8px 18px rgba(139, 92, 246, 0.3);
    }
    .search-btn:hover { background: var(--primary-glow); transform: scale(1.02); }
    .search-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

    .status-message {
      text-align: center; padding: 2rem; color: var(--text-muted);
      font-size: 1.1rem; background: var(--card-bg);
      border-radius: var(--radius); border: 1px solid var(--border);
    }

    .song-grid {
      display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 1.5rem; margin-top: 1.5rem;
    }
    @media (max-width: 600px) { .song-grid { grid-template-columns: 1fr; } }

    .song-card {
      background: var(--card-bg); border-radius: var(--radius); padding: 1.25rem;
      border: 1px solid var(--border); transition: var(--transition);
      display: flex; flex-direction: column; gap: 1rem;
    }
    .song-card:hover {
      background: var(--card-hover); border-color: var(--primary);
      transform: translateY(-4px);
    }

    .thumbnail-container {
      position: relative; border-radius: var(--radius-sm); overflow: hidden;
      aspect-ratio: 16 / 9; background: #1a1a24;
    }
    .thumbnail-container img { width: 100%; height: 100%; object-fit: cover; display: block; }

    .song-info { display: flex; flex-direction: column; gap: 0.25rem; }
    .song-title {
      font-weight: 700; font-size: 1.1rem; line-height: 1.3;
      display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
      overflow: hidden;
    }
    .channel-name {
      color: var(--text-muted); font-size: 0.9rem;
      display: flex; align-items: center; gap: 0.4rem;
    }

    .card-actions { display: flex; gap: 0.6rem; margin-top: 0.25rem; }

    .btn {
      border: none; border-radius: 2.5rem; padding: 0.7rem 1.2rem;
      font-weight: 600; font-size: 0.95rem; display: inline-flex;
      align-items: center; justify-content: center; gap: 0.5rem;
      cursor: pointer; transition: var(--transition);
      background: var(--card-bg); color: var(--text);
      border: 1px solid var(--border); flex: 1;
    }
    .btn-primary { background: var(--primary); border: none; color: white; }
    .btn-primary:hover { background: var(--primary-glow); }
    .btn-outline { background: transparent; border: 1px solid var(--border); }
    .btn-outline:hover { background: rgba(255, 255, 255, 0.05); border-color: var(--primary); }
    .btn-danger { background: transparent; border: 1px solid #7f1d1d; color: #f87171; }
    .btn-danger:hover { background: #7f1d1d33; }
    .btn-small { padding: 0.5rem 1rem; font-size: 0.85rem; }
    .btn:disabled { opacity: 0.4; cursor: not-allowed; }

    .player-layout {
      display: grid; grid-template-columns: 1fr 380px; gap: 2rem; margin-top: 1.5rem;
    }
    @media (max-width: 1000px) { .player-layout { grid-template-columns: 1fr; } }

    .player-main {
      background: var(--card-bg); border-radius: var(--radius);
      padding: 1.5rem; border: 1px solid var(--border);
    }

    .video-wrapper {
      position: relative; border-radius: var(--radius-sm); overflow: hidden;
      aspect-ratio: 16 / 9; background: black; margin-bottom: 1.2rem;
    }
    #youtube-player, .video-wrapper iframe { width: 100%; height: 100%; border: none; }

    .now-playing-info h2 { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.3rem; }
    .now-playing-info p {
      color: var(--text-muted); display: flex; align-items: center;
      gap: 0.5rem; margin-bottom: 1rem;
    }

    .player-controls { display: flex; flex-wrap: wrap; gap: 0.75rem; margin: 1.5rem 0 0.5rem; }

    .queue-sidebar {
      background: var(--card-bg); border-radius: var(--radius);
      padding: 1.5rem; border: 1px solid var(--border);
      height: fit-content; max-height: 80vh; overflow-y: auto;
    }

    .queue-header {
      display: flex; justify-content: space-between; align-items: center;
      margin-bottom: 1.2rem;
    }
    .queue-header h3 { font-size: 1.3rem; display: flex; align-items: center; gap: 0.6rem; }

    .queue-list { display: flex; flex-direction: column; gap: 0.9rem; }

    .queue-item {
      display: flex; align-items: center; gap: 0.9rem;
      background: var(--bg); padding: 0.8rem; border-radius: var(--radius-sm);
      border: 1px solid var(--border); transition: var(--transition);
    }
    .queue-item:hover { border-color: var(--primary); }

    .queue-thumb {
      width: 70px; height: 45px; border-radius: 0.5rem; object-fit: cover;
      background: #1a1a24; flex-shrink: 0;
    }

    .queue-info { flex: 1; min-width: 0; }
    .queue-info .q-title {
      font-weight: 600; font-size: 0.9rem; white-space: nowrap;
      overflow: hidden; text-overflow: ellipsis;
    }
    .queue-info .q-channel {
      font-size: 0.75rem; color: var(--text-muted);
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }

    .queue-actions { display: flex; gap: 0.3rem; }

    .icon-btn {
      background: transparent; border: none; color: var(--text-muted);
      width: 34px; height: 34px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      cursor: pointer; transition: var(--transition); font-size: 1rem;
    }
    .icon-btn:hover { background: rgba(255, 255, 255, 0.1); color: white; }
    .icon-btn.play-now:hover { color: #8b5cf6; }
    .icon-btn.remove:hover { color: #f87171; }

    .empty-queue {
      color: var(--text-muted); text-align: center;
      padding: 2rem 1rem; font-size: 0.95rem;
    }

    .view { display: none; }
    .view.active { display: block; }

    .spinner {
      width: 40px; height: 40px;
      border: 4px solid rgba(139, 92, 246, 0.2);
      border-top: 4px solid var(--primary); border-radius: 50%;
      animation: spin 0.8s linear infinite; margin: 2rem auto;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    .error-box {
      background: #2a1215; border: 1px solid #7f1d1d; color: #f87171;
      padding: 1rem 1.5rem; border-radius: var(--radius-sm); margin: 1rem 0;
    }

    .video-wrapper:fullscreen { border-radius: 0; aspect-ratio: auto; height: 100vh; }
    .video-wrapper:-webkit-full-screen { border-radius: 0; aspect-ratio: auto; height: 100vh; }

    .hidden { display: none; }

    .live-badge {
      display: inline-flex; align-items: center; gap: 0.4rem;
      background: rgba(139, 92, 246, 0.15);
      border: 1px solid rgba(139, 92, 246, 0.4);
      color: #c4b5fd;
      font-size: 0.7rem; font-weight: 700;
      padding: 0.25rem 0.7rem; border-radius: 2rem;
      text-transform: uppercase; letter-spacing: 0.05em;
      margin-left: 0.6rem;
    }
    .live-dot {
      width: 7px; height: 7px; border-radius: 50%;
      background: #22c55e;
      box-shadow: 0 0 8px #22c55e;
      animation: pulse 1.5s ease-in-out infinite;
    }
    @keyframes pulse {
      0%, 100% { opacity: 1; transform: scale(1); }
      50% { opacity: 0.5; transform: scale(1.3); }
    }

    .conn-badge {
      display: inline-flex; align-items: center; gap: 0.4rem;
      font-size: 0.7rem; font-weight: 700;
      padding: 0.25rem 0.7rem; border-radius: 2rem;
      text-transform: uppercase; letter-spacing: 0.05em;
      background: rgba(107, 114, 128, 0.2);
      border: 1px solid rgba(107, 114, 128, 0.4);
      color: #9ca3af;
    }
    .conn-badge.online {
      background: rgba(34, 197, 94, 0.15);
      border-color: rgba(34, 197, 94, 0.4);
      color: #4ade80;
    }
    .conn-badge.offline {
      background: rgba(239, 68, 68, 0.15);
      border-color: rgba(239, 68, 68, 0.4);
      color: #f87171;
    }
    .conn-dot {
      width: 6px; height: 6px; border-radius: 50%;
      background: currentColor;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translate(-50%, 20px); }
      to { opacity: 1; transform: translate(-50%, 0); }
    }
  </style>
</head>
<body>
<div class="app-container">
  <header class="app-header">
    <div class="logo">
      <i class="fas fa-microphone-alt"></i>
      <span>Karaoke / Videoke</span>
      <span class="live-badge"><span class="live-dot"></span> Public Queue</span>
      <span class="conn-badge" id="connBadge"><span class="conn-dot"></span> Connecting…</span>
    </div>
    <nav class="nav-links">
      <button class="nav-btn active" data-view="home"><i class="fas fa-search"></i> Request a Song</button>
      <button class="nav-btn" data-view="player"><i class="fas fa-play"></i> Player</button>
      <button class="nav-btn" data-view="queue"><i class="fas fa-list"></i> Live Queue</button>
    </nav>
  </header>

  <section id="view-home" class="view active">
    <div class="search-section">
      <div class="search-bar">
        <input type="text" id="searchInput" class="search-input" placeholder="🔍 Search any karaoke song, artist, or OPM...">
        <button id="searchBtn" class="search-btn"><i class="fas fa-search"></i> Search</button>
      </div>
      <div id="searchStatus" class="status-message hidden"></div>
      <div id="searchResults" class="song-grid"></div>
    </div>
  </section>

  <section id="view-player" class="view">
    <div class="player-layout">
      <div class="player-main">
        <div class="video-wrapper" id="playerWrapper">
          <div id="youtube-player"></div>
        </div>
        <div class="now-playing-info" id="nowPlayingInfo">
          <h2 id="nowPlayingTitle">No song selected</h2>
          <p id="nowPlayingChannel"><i class="fas fa-tv"></i> Select a song to play</p>
        </div>
        <div class="player-controls">
          <button class="btn btn-outline" id="prevBtn"><i class="fas fa-backward"></i> Previous</button>
          <button class="btn btn-primary" id="playPauseBtn"><i class="fas fa-pause"></i> Pause</button>
          <button class="btn btn-outline" id="nextBtn">Next <i class="fas fa-forward"></i></button>
          <button class="btn btn-outline" id="fullscreenBtn"><i class="fas fa-expand"></i> Fullscreen</button>
          <button class="btn btn-outline" id="addCurrentToQueueBtn"><i class="fas fa-plus"></i> Add to Queue</button>
        </div>
      </div>
      <aside class="queue-sidebar">
        <div class="queue-header">
          <h3><i class="fas fa-list"></i> Up Next <span class="live-badge"><span class="live-dot"></span> Live</span></h3>
          <button class="btn btn-small btn-danger" id="clearQueueBtn"><i class="fas fa-trash-alt"></i> Clear</button>
        </div>
        <div id="playerQueueList" class="queue-list"></div>
      </aside>
    </div>
  </section>

  <section id="view-queue" class="view">
    <div class="queue-sidebar" style="max-height: none; width: 100%;">
      <div class="queue-header">
        <h3><i class="fas fa-list"></i> Public Song Queue <span class="live-badge"><span class="live-dot"></span> Live Sync</span></h3>
        <button class="btn btn-small btn-danger" id="clearQueueBtnFull"><i class="fas fa-trash-alt"></i> Clear All</button>
      </div>
      <div id="fullQueueList" class="queue-list"></div>
    </div>
  </section>
</div>

<script src="https://www.youtube.com/iframe_api"></script>
<script>
  // ============================================================
  //  CONFIG
  // ============================================================
  const API_KEY = 'AIzaSyDA0fpvDIw7fnLVRI-akjiuoqavrsbDHBw';
  const YOUTUBE_SEARCH_URL = 'https://www.googleapis.com/youtube/v3/search';
  const YOUTUBE_VIDEOS_URL = 'https://www.googleapis.com/youtube/v3/videos';

  const PUSHER_APP_KEY = '7580414d81220f9bb60b';
  const PUSHER_CLUSTER = 'ap1';
  const PUSHER_CHANNEL = 'karaoke-public-room';
  const PUSHER_EVENT   = 'queue-update';

  const API_STATE   = 'index.php?action=state';
  const API_ADD     = 'index.php?action=add';
  const API_REMOVE  = 'index.php?action=remove';
  const API_CLEAR   = 'index.php?action=clear';
  const API_CURRENT = 'index.php?action=current';
  const API_NEXT    = 'index.php?action=next';

  let pusher = null;
  let channel = null;
  let pusherConnected = false;

  // ========== STATE ==========
  let queue = [];
  let currentSong = null;
  let lastVersion = -1;
  let player = null;
  let playerReady = false;
  let isPlaying = false;
  let ytApiReady = false;
  let pendingVideoId = null;
  let errorSkipTimeout = null;

  // ========== DOM ==========
  const views = {
    home: document.getElementById('view-home'),
    player: document.getElementById('view-player'),
    queue: document.getElementById('view-queue')
  };
  const navBtns = document.querySelectorAll('.nav-btn[data-view]');
  const searchInput = document.getElementById('searchInput');
  const searchBtn = document.getElementById('searchBtn');
  const searchStatus = document.getElementById('searchStatus');
  const searchResults = document.getElementById('searchResults');

  const nowPlayingTitle = document.getElementById('nowPlayingTitle');
  const nowPlayingChannel = document.getElementById('nowPlayingChannel');
  const playerWrapper = document.getElementById('playerWrapper');
  const playPauseBtn = document.getElementById('playPauseBtn');
  const prevBtn = document.getElementById('prevBtn');
  const nextBtn = document.getElementById('nextBtn');
  const fullscreenBtn = document.getElementById('fullscreenBtn');
  const addCurrentToQueueBtn = document.getElementById('addCurrentToQueueBtn');

  const playerQueueList = document.getElementById('playerQueueList');
  const fullQueueList = document.getElementById('fullQueueList');
  const clearQueueBtn = document.getElementById('clearQueueBtn');
  const clearQueueBtnFull = document.getElementById('clearQueueBtnFull');
  const connBadge = document.getElementById('connBadge');

  // ============================================================
  //  SERVER STATE SYNC
  // ============================================================
  async function fetchState() {
    try {
      const r = await fetch(API_STATE + '&_=' + Date.now(), { cache: 'no-store' });
      if (!r.ok) throw new Error('HTTP ' + r.status);
      const data = await r.json();
      applyState(data);
      return data;
    } catch (e) {
      console.warn('[state] fetch failed:', e);
      return null;
    }
  }

  function applyState(data) {
    if (!data || typeof data !== 'object') return;

    const v = typeof data.version === 'number' ? data.version : 0;
    if (v === lastVersion) return;   // nothing changed
    lastVersion = v;

    queue = Array.isArray(data.queue) ? data.queue : [];
    currentSong = data.current || null;

    updateAllQueueUIs();
    refreshNowPlayingUI();
  }

  // ============================================================
  //  PUSHER
  // ============================================================
  function updateConnBadge() {
    if (!connBadge) return;
    if (pusherConnected) {
      connBadge.className = 'conn-badge online';
      connBadge.innerHTML = '<span class="conn-dot"></span> Live';
    } else {
      connBadge.className = 'conn-badge offline';
      connBadge.innerHTML = '<span class="conn-dot"></span> Offline';
    }
  }

  function initPusher() {
    if (pusher) return;
    try {
      pusher = new Pusher(PUSHER_APP_KEY, {
        cluster: PUSHER_CLUSTER,
        forceTLS: true
      });

      channel = pusher.subscribe(PUSHER_CHANNEL);

      channel.bind(PUSHER_EVENT, (msg) => {
        console.log('[Pusher] ←', msg && msg.type);
        // Every event → re-fetch authoritative state from server
        fetchState();
      });

      pusher.connection.bind('connected', () => {
        console.log('[Pusher] connected');
        pusherConnected = true;
        updateConnBadge();
        fetchState();
      });

      pusher.connection.bind('disconnected', () => {
        pusherConnected = false;
        updateConnBadge();
      });

      pusher.connection.bind('error', (err) => {
        console.warn('[Pusher] error:', err);
        pusherConnected = false;
        updateConnBadge();
      });

      pusher.connection.bind('state_change', (s) => {
        if (s.current === 'connected') pusherConnected = true;
        else if (s.current === 'disconnected' || s.current === 'failed') pusherConnected = false;
        updateConnBadge();
      });
    } catch (e) {
      console.warn('[Pusher] init failed:', e);
      updateConnBadge();
    }
  }

  // ============================================================
  //  SERVER MUTATIONS
  // ============================================================
  async function apiAdd(song) {
    const r = await fetch(API_ADD, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(song)
    });
    const data = await r.json();
    if (data.state) applyState(data.state);
    return data;
  }

  async function apiRemove(videoId) {
    const r = await fetch(API_REMOVE, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ videoId })
    });
    const data = await r.json();
    if (data && data.queue) applyState(data);
    return data;
  }

  async function apiClear() {
    const r = await fetch(API_CLEAR, { method: 'POST' });
    const data = await r.json();
    if (data && data.queue) applyState(data);
    return data;
  }

  async function apiSetCurrent(song) {
    const r = await fetch(API_CURRENT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ current: song })
    });
    const data = await r.json();
    if (data && data.current !== undefined) applyState(data);
    return data;
  }

  async function apiNext() {
    const r = await fetch(API_NEXT, { method: 'POST' });
    const data = await r.json();
    if (data && data.queue) applyState(data);
    return data;
  }

  // ============================================================
  //  YOUTUBE SEARCH
  // ============================================================
  async function searchYouTube(query) {
    if (!query.trim()) return;
    setSearchLoading(true);
    try {
      const params = new URLSearchParams({
        part: 'snippet',
        q: query + ' karaoke',
        type: 'video',
        maxResults: 25,
        key: API_KEY,
        videoEmbeddable: 'true',
        videoSyndicated: 'true'
      });
      const response = await fetch(`${YOUTUBE_SEARCH_URL}?${params}`);
      if (!response.ok) {
        const err = await response.json();
        throw new Error(err.error?.message || 'YouTube API error');
      }
      const data = await response.json();
      const filteredItems = await filterEmbeddableVideos(data.items || []);
      renderSearchResults(filteredItems);
    } catch (err) {
      showSearchError('❌ ' + err.message);
    } finally {
      setSearchLoading(false);
    }
  }

  async function filterEmbeddableVideos(items) {
    if (!items.length) return [];
    const videoIds = items.map(i => i.id.videoId).filter(Boolean).join(',');
    if (!videoIds) return items;

    try {
      const params = new URLSearchParams({
        part: 'status,contentDetails',
        id: videoIds,
        key: API_KEY
      });
      const response = await fetch(`${YOUTUBE_VIDEOS_URL}?${params}`);
      if (!response.ok) return items;
      const data = await response.json();

      const allowed = new Set(
        (data.items || [])
          .filter(v => v.status?.embeddable === true && v.status?.privacyStatus === 'public')
          .map(v => v.id)
      );

      return items.filter(item => allowed.has(item.id.videoId));
    } catch (e) {
      console.warn('Embeddable filter failed, showing all:', e);
      return items;
    }
  }

  function setSearchLoading(loading) {
    searchBtn.disabled = loading;
    if (loading) {
      searchStatus.classList.remove('hidden');
      searchStatus.innerHTML = '<div class="spinner"></div><p style="margin-top:0.5rem;">Searching YouTube...</p>';
      searchResults.innerHTML = '';
    } else {
      searchStatus.classList.add('hidden');
    }
  }

  function showSearchError(msg) {
    searchStatus.classList.remove('hidden');
    searchStatus.innerHTML = `<div class="error-box"><i class="fas fa-exclamation-circle"></i> ${msg}</div>`;
    searchResults.innerHTML = '';
  }

  function renderSearchResults(items) {
    if (!items || items.length === 0) {
      searchStatus.classList.remove('hidden');
      searchStatus.innerHTML = '<div class="status-message"><i class="fas fa-search-minus"></i> No playable karaoke songs found. Try a different search.</div>';
      searchResults.innerHTML = '';
      return;
    }

    searchStatus.classList.add('hidden');
    searchResults.innerHTML = items.map(item => {
      const videoId = item.id.videoId;
      const title = item.snippet.title;
      const channel = item.snippet.channelTitle;
      const thumbnail = item.snippet.thumbnails.medium?.url || item.snippet.thumbnails.default?.url;

      return `
        <div class="song-card" data-video-id="${videoId}" data-title="${escapeHtml(title)}" data-channel="${escapeHtml(channel)}" data-thumb="${thumbnail}">
          <div class="thumbnail-container">
            <img src="${thumbnail}" alt="${escapeHtml(title)}" loading="lazy">
          </div>
          <div class="song-info">
            <div class="song-title">${escapeHtml(title)}</div>
            <div class="channel-name"><i class="fas fa-user-circle"></i> ${escapeHtml(channel)}</div>
          </div>
          <div class="card-actions">
            <button class="btn btn-primary play-btn"><i class="fas fa-play"></i> Play Now</button>
            <button class="btn btn-outline add-queue-btn"><i class="fas fa-plus"></i> Request</button>
          </div>
        </div>
      `;
    }).join('');

    document.querySelectorAll('.song-card').forEach(card => {
      const videoId = card.dataset.videoId;
      const title = card.dataset.title;
      const channel = card.dataset.channel;
      const thumbnail = card.dataset.thumb;

      card.querySelector('.play-btn').addEventListener('click', (e) => {
        e.stopPropagation();
        playSong({ videoId, title, channel, thumbnail });
      });

      card.querySelector('.add-queue-btn').addEventListener('click', (e) => {
        e.stopPropagation();
        addToQueue({ videoId, title, channel, thumbnail });
      });
    });
  }

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  // ============================================================
  //  YOUTUBE PLAYER
  // ============================================================
  function initYouTubePlayer() {
    if (!ytApiReady || player) return;
    player = new YT.Player('youtube-player', {
      height: '100%',
      width: '100%',
      videoId: '',
      playerVars: {
        autoplay: 0,
        controls: 1,
        rel: 0,
        modestbranding: 1,
        fs: 1,
        playsinline: 1,
        origin: window.location.origin
      },
      events: {
        onReady: onPlayerReady,
        onStateChange: onPlayerStateChange,
        onError: onPlayerError
      }
    });
  }

  function onPlayerReady(event) {
    playerReady = true;
    if (pendingVideoId) {
      safeLoadVideo(pendingVideoId);
      pendingVideoId = null;
    }
    updatePlayPauseButton();
  }

  function safeLoadVideo(videoId) {
    if (errorSkipTimeout) { clearTimeout(errorSkipTimeout); errorSkipTimeout = null; }
    try {
      player.loadVideoById(videoId);
      errorSkipTimeout = setTimeout(() => {
        if (!isPlaying && currentSong && currentSong.videoId === videoId) {
          console.warn('Video did not start, skipping...');
          showPlayerToast('⚠️ This video is unavailable. Skipping...');
          requestNext();
        }
      }, 6000);
    } catch (e) {
      console.warn('loadVideoById failed:', e);
      requestNext();
    }
  }

  function onPlayerStateChange(event) {
    isPlaying = (event.data === YT.PlayerState.PLAYING);
    updatePlayPauseButton();

    if (event.data === YT.PlayerState.PLAYING && errorSkipTimeout) {
      clearTimeout(errorSkipTimeout);
      errorSkipTimeout = null;
    }

    if (event.data === YT.PlayerState.ENDED) {
      requestNext();
    }
  }

  function onPlayerError(event) {
    console.warn('YouTube player error code:', event.data);
    showPlayerToast('⚠️ This video cannot be played. Skipping...');
    setTimeout(() => requestNext(), 1200);
  }

  function showPlayerToast(message) {
    let toast = document.getElementById('playerToast');
    if (!toast) {
      toast = document.createElement('div');
      toast.id = 'playerToast';
      toast.style.cssText = `
        position: fixed; bottom: 2rem; left: 50%; transform: translateX(-50%);
        background: #2a1215; border: 1px solid #7f1d1d; color: #f87171;
        padding: 0.9rem 1.6rem; border-radius: 3rem; font-weight: 600;
        z-index: 9999; box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        animation: fadeIn 0.3s ease;
      `;
      document.body.appendChild(toast);
    }
    toast.textContent = message;
    toast.style.display = 'block';
    clearTimeout(toast._hideTimer);
    toast._hideTimer = setTimeout(() => { toast.style.display = 'none'; }, 2500);
  }

  function updatePlayPauseButton() {
    if (!playPauseBtn) return;
    playPauseBtn.innerHTML = isPlaying
      ? '<i class="fas fa-pause"></i> Pause'
      : '<i class="fas fa-play"></i> Play';
  }

  // ============================================================
  //  QUEUE ACTIONS (all go through the server)
  // ============================================================
  async function addToQueue(song) {
    if (!song || !song.videoId) return;
    const res = await apiAdd(song);
    if (res && res.ok === false && res.reason === 'already_queued') {
      showPlayerToast('Already in the queue.');
    } else {
      showPlayerToast('✅ Song requested!');
    }
  }

  async function removeFromQueue(videoId) {
    await apiRemove(videoId);
  }

  async function clearQueue() {
    if (queue.length === 0) return;
    if (confirm('Clear the entire public queue?')) {
      await apiClear();
    }
  }

  async function playSong(song) {
    if (!song || !song.videoId) return;

    // If the song is currently in the queue, remove it (server-side)
    if (queue.some(s => s.videoId === song.videoId)) {
      await apiRemove(song.videoId);
    }

    await apiSetCurrent(song);

    if (player && playerReady) {
      safeLoadVideo(song.videoId);
    } else {
      pendingVideoId = song.videoId;
      initYouTubePlayer();
    }

    switchView('player');
  }

  async function requestNext() {
    await apiNext();
    // Server-side queue shifted; play whatever is now "current"
    if (currentSong && player && playerReady) {
      safeLoadVideo(currentSong.videoId);
    }
  }

  function playPrevious() {
    if (currentSong && player && playerReady) safeLoadVideo(currentSong.videoId);
  }

  // ============================================================
  //  RENDER
  // ============================================================
  function updateAllQueueUIs() {
    renderQueueList(playerQueueList);
    renderQueueList(fullQueueList);
  }

  function renderQueueList(container) {
    if (!container) return;
    if (!queue || queue.length === 0) {
      container.innerHTML = '<div class="empty-queue"><i class="fas fa-music"></i> Queue is empty. Request a song!</div>';
      return;
    }
    container.innerHTML = queue.map((song) => `
      <div class="queue-item" data-video-id="${song.videoId}">
        <img class="queue-thumb" src="${song.thumbnail}" alt="${escapeHtml(song.title)}" loading="lazy">
        <div class="queue-info">
          <div class="q-title">${escapeHtml(song.title)}</div>
          <div class="q-channel">${escapeHtml(song.channel)}</div>
        </div>
        <div class="queue-actions">
          <button class="icon-btn play-now" title="Play now"><i class="fas fa-play"></i></button>
          <button class="icon-btn remove" title="Remove"><i class="fas fa-times"></i></button>
        </div>
      </div>
    `).join('');

    container.querySelectorAll('.queue-item').forEach(item => {
      const videoId = item.dataset.videoId;
      const song = queue.find(s => s.videoId === videoId);
      if (!song) return;

      item.querySelector('.play-now').addEventListener('click', (e) => {
        e.stopPropagation();
        playSong(song);
      });

      item.querySelector('.remove').addEventListener('click', (e) => {
        e.stopPropagation();
        removeFromQueue(videoId);
      });
    });
  }

  function refreshNowPlayingUI() {
    if (currentSong) {
      nowPlayingTitle.textContent = currentSong.title;
      nowPlayingChannel.innerHTML = `<i class="fas fa-user-circle"></i> ${currentSong.channel}`;
    } else {
      nowPlayingTitle.textContent = 'No song selected';
      nowPlayingChannel.innerHTML = '<i class="fas fa-tv"></i> Select a song to play';
    }
  }

  // ============================================================
  //  VIEW SWITCHING
  // ============================================================
  function switchView(viewName) {
    Object.keys(views).forEach(key => {
      views[key].classList.toggle('active', key === viewName);
    });
    navBtns.forEach(btn => {
      btn.classList.toggle('active', btn.dataset.view === viewName);
    });
    if (viewName === 'player' && !player) initYouTubePlayer();
  }

  // ============================================================
  //  EVENT LISTENERS
  // ============================================================
  function setupEventListeners() {
    searchBtn.addEventListener('click', () => searchYouTube(searchInput.value));
    searchInput.addEventListener('keypress', (e) => {
      if (e.key === 'Enter') searchYouTube(searchInput.value);
    });

    navBtns.forEach(btn => {
      btn.addEventListener('click', () => switchView(btn.dataset.view));
    });

    playPauseBtn.addEventListener('click', () => {
      if (!player || !playerReady) return;
      if (isPlaying) player.pauseVideo();
      else player.playVideo();
    });

    prevBtn.addEventListener('click', playPrevious);

    nextBtn.addEventListener('click', requestNext);

    fullscreenBtn.addEventListener('click', () => {
      const elem = playerWrapper;
      if (elem.requestFullscreen) elem.requestFullscreen();
      else if (elem.webkitRequestFullscreen) elem.webkitRequestFullscreen();
      else if (elem.msRequestFullscreen) elem.msRequestFullscreen();
    });

    addCurrentToQueueBtn.addEventListener('click', () => {
      if (currentSong) addToQueue(currentSong);
      else showPlayerToast('No song is currently playing.');
    });

    clearQueueBtn.addEventListener('click', clearQueue);
    clearQueueBtnFull.addEventListener('click', clearQueue);

    window.addEventListener('keydown', (e) => {
      if (e.target.tagName === 'INPUT') return;
      if (e.code === 'Space' && views.player.classList.contains('active')) {
        e.preventDefault();
        playPauseBtn.click();
      }
      if (e.code === 'ArrowRight' && views.player.classList.contains('active')) nextBtn.click();
      if (e.code === 'ArrowLeft' && views.player.classList.contains('active')) prevBtn.click();
    });
  }

  // ============================================================
  //  INIT
  // ============================================================
  window.onYouTubeIframeAPIReady = function() {
    ytApiReady = true;
    initYouTubePlayer();
  };

  document.addEventListener('DOMContentLoaded', async () => {
    initPusher();
    setupEventListeners();

    // Load initial state from server
    await fetchState();

    // Safety net: poll every 10 seconds in case Pusher misses an event
    setInterval(fetchState, 10000);

    if (currentSong) {
      if (player && playerReady) player.cueVideoById(currentSong.videoId);
      else pendingVideoId = currentSong.videoId;
    }
    if (ytApiReady) initYouTubePlayer();
  });
</script>
</body>
</html>
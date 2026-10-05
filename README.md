<div align="center">

<svg width="720" height="150" viewBox="0 0 720 150" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="SkyMineZ">
  <defs>
    <linearGradient id="sky" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="#0b1026"/>
      <stop offset="1" stop-color="#1b2a5e"/>
    </linearGradient>
    <linearGradient id="ore" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#c084fc"/>
      <stop offset="1" stop-color="#7c3aed"/>
    </linearGradient>
  </defs>
  <rect x="4" y="4" width="712" height="142" rx="18" fill="url(#sky)" stroke="#7c3aed" stroke-width="2"/>
  <g fill="#c084fc">
    <rect x="96" y="52" width="14" height="14" rx="2"/>
    <rect x="114" y="52" width="14" height="14" rx="2" fill="#f0abfc"/>
    <rect x="96" y="70" width="14" height="14" rx="2" fill="#f0abfc"/>
    <rect x="114" y="70" width="14" height="14" rx="2"/>
    <rect x="96" y="88" width="14" height="14" rx="2"/>
    <rect x="114" y="88" width="14" height="14" rx="2" fill="#f0abfc"/>
  </g>
  <rect x="88" y="44" width="48" height="66" rx="4" fill="none" stroke="#c084fc" stroke-width="2"/>
  <text x="160" y="82" font-family="Verdana, sans-serif" font-size="44" font-weight="bold" fill="#e9d5ff">SkyMine<tspan fill="#f0abfc">Z</tspan></text>
  <text x="162" y="108" font-family="Verdana, sans-serif" font-size="15" fill="#a5b4fc">mines &#183; crates &#183; outposts &#183; zero-lag refills</text>
</svg>

<svg width="560" height="28" viewBox="0 0 560 28" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="build badges">
  <g font-family="Verdana, sans-serif" font-size="11">
    <rect x="0" y="0" width="150" height="24" rx="4" fill="#1b2a5e"/>
    <rect x="0" y="0" width="52" height="24" rx="4" fill="#0b1026"/>
    <text x="8" y="16" fill="#a5b4fc">pmmp</text>
    <text x="60" y="16" fill="#e9d5ff">5.44 &#183; PHP 8.1+</text>
    <rect x="160" y="0" width="150" height="24" rx="4" fill="#1b2a5e"/>
    <rect x="160" y="0" width="62" height="24" rx="4" fill="#0b1026"/>
    <text x="168" y="16" fill="#a5b4fc">phpstan</text>
    <text x="230" y="16" fill="#7CFC00">level 9 clean</text>
    <rect x="320" y="0" width="230" height="24" rx="4" fill="#1b2a5e"/>
    <rect x="320" y="0" width="70" height="24" rx="4" fill="#0b1026"/>
    <text x="328" y="16" fill="#a5b4fc">server-tested</text>
    <text x="398" y="16" fill="#e9d5ff">boots &#183; commands pass</text>
  </g>
</svg>

</div>

# SkyMineZ

A SkyMine plugin for PocketMine-MP (Empty-NG fork) that treats **tick time as a
budget**: mine refills are spread across ticks, player scans are batched, and
lookups that run on every interaction are O(1) indexes instead of linear scans.

Inspired by the SkyMine mode of `play.bitonetop.com`.

---

## Systems

| System | What it does |
| --- | --- |
| `mine/` | Cuboid mines with weighted block lists, hologram countdowns, automatic timed refills via a per-tick block budget (`MineFillTask`), grief protection, full JSON persistence |
| `crate/` | Animated chest crates with weighted rewards, NBT key items, read-only previews, explosion/pairing protection, O(1) position index |
| `outpost/` | Capturable zones with progress, cooldowns, owner gold payouts, holograms, JSON persistence |
| `slapper/` | Clickable NPCs (custom skin, messages, console/player commands) plus clickable blocks bound to them |
| `leaderboard/` | Floating top-10 holograms for money, gold, mined, deaths and kills; hash-guarded re-renders (no flicker) |
| `scorehud/` | Sidebar with spawn welcome screen and stats screen, hysteresis switching, delta-only packet updates, per-player toggle |
| `economy/` | Dual JSON currency (`money`, `gold`) with vetoable change events |
| `miner/` | Per-player mined/deaths/kills/streak stats |
| `pvp/` | Per-player PvP preference with projectile attribution |
| `lagmaker/` | Item-entity stacking, per-player drop caps, TTL/all/off cleanup passes — every pass tick-spread |
| `form/` | Bundled FormAPI port (Simple/Custom/Modal) compatible with PM 5.44 |
| `event/` | Public cancellable events for other plugins |
| `useless/` | `SpreadTask` (tick-spread iteration), hologram particles, `NumberFormatter`, read-only inventories |

---

## How the lag stays away

<svg width="720" height="170" viewBox="0 0 720 170" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="tick budget diagram">
  <g font-family="Verdana, sans-serif">
    <rect x="4" y="4" width="712" height="162" rx="12" fill="#0b1026" stroke="#334155" stroke-width="1"/>
    <text x="24" y="32" font-size="14" font-weight="bold" fill="#e9d5ff">One 50 ms tick, one 50,000-block mine refill</text>
    <text x="24" y="58" font-size="12" fill="#f87171">before: setBlockAt x 50,000 in a single tick &#8212; the server freezes</text>
    <rect x="24" y="68" width="672" height="16" rx="3" fill="#7f1d1d"/>
    <rect x="24" y="68" width="672" height="16" rx="3" fill="#ef4444"/>
    <text x="24" y="108" font-size="12" fill="#7CFC00">after: mines.blocks-per-tick (default 3,000) per tick &#8212; ~17 ticks, players lifted out first</text>
    <rect x="24" y="118" width="672" height="16" rx="3" fill="#14532d"/>
    <g fill="#22c55e">
      <rect x="24" y="118" width="36" height="16"/><rect x="64" y="118" width="36" height="16"/><rect x="104" y="118" width="36" height="16"/><rect x="144" y="118" width="36" height="16"/><rect x="184" y="118" width="36" height="16"/><rect x="224" y="118" width="36" height="16"/><rect x="264" y="118" width="36" height="16"/><rect x="304" y="118" width="36" height="16"/><rect x="344" y="118" width="36" height="16"/><rect x="384" y="118" width="36" height="16"/><rect x="424" y="118" width="36" height="16"/><rect x="464" y="118" width="36" height="16"/><rect x="504" y="118" width="36" height="16"/><rect x="544" y="118" width="36" height="16"/><rect x="584" y="118" width="36" height="16"/><rect x="624" y="118" width="36" height="16"/><rect x="664" y="118" width="32" height="16"/>
    </g>
    <text x="24" y="156" font-size="12" fill="#a5b4fc">same pattern everywhere: sidebar passes, join hologram bursts and cleanup sweeps all run through SpreadTask</text>
  </g>
</svg>

- **`useless/SpreadTask`** — one-shot task that walks any list N entries per
  tick, then cancels itself. Used by the sidebar tick, every join-hologram
  burst, and the lag-cleaner queue builder. (One-shot matters: scheduling it as
  repeating would leak a task per tick — an earlier revision of this plugin did
  exactly that.)
- **`mine/MineFillTask`** — refill writes at most `mines.blocks-per-tick`
  blocks per tick with arithmetically derived coordinates (no 50k position array
  allocated up front).
- **O(1) indexes** — crates and slapper blocks are resolved by a
  `world:x:y:z` hash, not by scanning every crate on every click.
- **Normalized collision boxes** — `min`/`max` are precomputed once, so the
  per-tick `isIn()` check is six float comparisons.
- **Delta-only sidebar** — unchanged scoreboard lines cost one string compare
  and zero packets.

---

## Installation

1. Requires **PocketMine-MP 5.44** (Empty-NG build) and **PHP 8.1+** with the
   `yaml` extension.
2. Copy this folder to `plugins/SkyMineZ/` (folder plugin — no `.phar` build
   needed) and start the server.
3. `resources/config.yml` is copied to `plugin_data/SkyMineZ/config.yml` on
   first run; edit it, then run `/skymine reload`.

---

## Commands

Player commands (`skyminez.use`, granted to everyone):

| Command | Effect |
| --- | --- |
| `/skymine menu` | Main menu form (PvP toggle, sidebar toggle, stats, admin shortcuts) |
| `/skymine pvp [on\|off]` | Toggle your PvP preference |
| `/skymine hud` | Toggle your sidebar |
| `/skymine stats [player]` | Mining stats, works for offline names too |
| `/skymine pos1` / `/skymine pos2` | Mark cuboid corners for `/mine create` and `/outpost create` |

Admin commands (`skyminez.admin`, op by default):

| Command | Effect |
| --- | --- |
| `/skymine money\|gold <give\|take\|set\|check> <player> [amount]` | Manage balances |
| `/skymine lagmaker <status\|toggle\|cleanup <off\|ttl\|all>>` | Lag protection controls |
| `/skymine reload` / `/skymine save` | Reload config+data / flush every store |
| `/crate create\|remove\|move\|list\|givekey\|open\|save` | Crates and keys |
| `/crate reward <add\|remove\|list\|weight\|type>` | Reward entries with live chance display |
| `/mine create\|remove\|list\|info\|reset\|setinterval\|setlabel\|clearblocks` | Mines |
| `/mine block <add\|remove\|list>` | Weighted block list (`/mine block add <mine> stone 60`) |
| `/mine pos1\|pos2` | Same markers, mine-flavoured aliases |
| `/outpost create\|remove\|list\|info\|owner\|reset` | Outposts (`owner <name> <player\|clear>`) |
| `/slapper create\|remove\|list\|move` | NPCs with your skin |
| `/slapper msg\|cmd <add\|remove\|clear\|list>` | Messages and commands (`{player}` placeholder) |
| `/slapper block <add\|remove\|list>` | Clickable blocks bound to a slapper |
| `/lb create\|remove\|list\|info\|title\|setpos\|refresh` | Leaderboards (`/lb create top money Top Money`) |

Typical first setup, in game:

```
/skymine pos1
/skymine pos2
/mine create coal
/mine block add coal coal_ore 40
/mine block add coal stone 60
/mine reset coal
/crate create vote
/crate givekey Steve vote 5
/outpost create mid
/lb create top money Top Balance
/slapper create guide
```

---

## Configuration

All tuning lives in `config.yml`: sidebar texts and timings, PvP defaults,
economy starting balances, crate animation pacing, mine refill budget and
default interval, outpost capture/cooldown/gold values, and the lag-cleaner
mode (`off` / `ttl` / `all`), TTL, interval and per-tick budget.

Data files (`plugin_data/SkyMineZ/*.json`) are plain JSON and safe to inspect;
`mines.json`, `outposts.json`, `crates.json`, `slappers.json` and
`leaderboards.json` survive restarts including owners, timers and block lists.

---

## For developers

- **Bundled forms** — `AM\SkyMineZ\form\FormAPI::simple($cb)->setTitle(...)->addButton(...)
  ->sendToPlayer($player)`. Same API as jojoe77777/FormAPI, plus the
  `onCompletion` / retry / blocking members PM 5.44 requires.
- **Events** — `CrateOpenEvent`, `MineResetEvent`, `OutpostCaptureEvent`,
  `SlapperInteractEvent`, `MinerBlockMinedEvent`, `EconomyChangeEvent`,
  `ScoreHudUpdateEvent`, `PlayerPvPChangeEvent`. All raised through
  `EventDispatcher::dispatch()`, which skips allocation entirely when nobody
  listens.
- **Static analysis** — `composer stan` (PHPStan level 9, clean). The PocketMine
  sources are server-provided; point `scanDirectories` in `phpstan.neon.dist`
  at your server checkout.
- **Verified on a live server** — the plugin boots on 5.44.2+dev and every
  console-safe command path was executed against a real server during
  development.

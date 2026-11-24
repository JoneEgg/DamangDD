<?php 
    require_once __DIR__ . '/../config/db.php';
    session_start();
    if(!isset($_SESSION['username'])){
        header("Location: ../auth/login.php");
        exit();
    }

        $username = $_SESSION['username'];
        $high_score = 0;
        $height_reached = 0;

        $select_sql = 'SELECT high_score, height_reached FROM scores WHERE user_name = $1';
        $result = pg_query_params($conn, $select_sql, [$username]);

        if ($result && $row = pg_fetch_assoc($result)) {
            $high_score = (int)$row['high_score'];
            $height_reached = (int)$row['height_reached'];
        }

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Main Menu</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous">
    <style>
        html, body {
            margin: 0;
            padding: 0;
            overflow: hidden;
            width: 100%;
            height: 100%;
        }

        #container {
            width: 100%;
            height: 100%;
            position: relative;
        }

        #nav_bar {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 20;
            background-color: #ffffff;
        }

        #nav_bar .nav.nav-tabs {
            justify-content: center;
        }

        #gameCanvas {
            position: fixed;
            left: 0;
            z-index: 1;
            display: block;
        }
    </style>
</head>
<body id="main_body">
    <div id="container">
        <div id="nav_bar">
            <ul class="nav nav-tabs">
                <li class="nav-item">
                    <a class="nav-link" aria-current="page" href="../game/main_game.php">Play</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" aria-current="page" href="../game/main_game_endless.php">Play Endless</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../game/scoreboard.php">Scoreboards</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../game/user_profile.php">Profile</a>
                </li>
            </ul>
        </div>

<canvas id="gameCanvas"></canvas>
<img id="spiderGifOverlay" src="../assets/images/dante.gif" alt="Dante" style="position: fixed; display: none; pointer-events: none; image-rendering: pixelated; z-index: 10;">
<img id="spiderChargeGifOverlay" src="../assets/images/dante_charge.gif" alt="Dante Charging" style="position: fixed; display: none; pointer-events: none; image-rendering: pixelated; z-index: 10;">
<img id="spiderWalkGifOverlay" src="../assets/images/dante_walk.gif" alt="Dante Walking" style="position: fixed; display: none; pointer-events: none; image-rendering: pixelated; z-index: 10;">

<img id="spiderInvulnGifOverlay" src="../assets/images/dante2.gif" alt="Dante Invulnerable" style="position: fixed; display: none; pointer-events: none; image-rendering: pixelated; z-index: 10;">
<img id="spiderChargeInvulnGifOverlay" src="../assets/images/dante_charge2.gif" alt="Dante Charging Invulnerable" style="position: fixed; display: none; pointer-events: none; image-rendering: pixelated; z-index: 10;">
<img id="spiderWalkInvulnGifOverlay" src="../assets/images/dante_walk2.gif" alt="Dante Walking Invulnerable" style="position: fixed; display: none; pointer-events: none; image-rendering: pixelated; z-index: 10;">

<script>
//#region Canvas and Session State
    const canvas = document.getElementById("gameCanvas");
    const ctx = canvas.getContext("2d");

    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
    const gameWidth = 600;
    let gameLeft = (canvas.width - gameWidth) / 2;

    const navBarEl = document.getElementById("nav_bar");
    let navHeight = 0;

    function syncViewportLayout() {
        navHeight = navBarEl ? Math.ceil(navBarEl.getBoundingClientRect().height) : 0;
        canvas.style.top = `${navHeight}px`;
        canvas.width = window.innerWidth;
        canvas.height = Math.max(200, window.innerHeight - navHeight);
        gameLeft = (canvas.width - gameWidth) / 2;
    }

    syncViewportLayout();

    let gameState = "playing"; // "playing" | "paused" | "gameover" | "victory"
    const victoryButtons = {
        retry:    { x: 0, y: 0, width: 180, height: 48 },
        mainMenu: { x: 0, y: 0, width: 180, height: 48 }
    };
    const pauseButton = { x: 0, y: 0, width: 170, height: 38 };
    const gameOverButtons = {
        retry:    { x: 0, y: 0, width: 180, height: 48 },
        mainMenu: { x: 0, y: 0, width: 180, height: 48 }
    };
    const musicToggleButton = { x: 0, y: 0, width: 170, height: 36 };
    const pauseButtons = {
        resume:   { x: 0, y: 0, width: 180, height: 48 },
        restart:  { x: 0, y: 0, width: 180, height: 48 },
        mainMenu: { x: 0, y: 0, width: 180, height: 48 }
    };
//#endregion

//#region Shared Sprite Helpers
        function createSpriteAsset(src, overlayId, fallbackMessage) {
        const image = new Image();
        const asset = {
            image,
            loaded: false,
            overlay: overlayId ? document.getElementById(overlayId) : null
        };

        image.onload = function() {
            asset.loaded = true;
        };
        image.onerror = function() {
            asset.loaded = false;
            if (fallbackMessage) {
                console.warn(fallbackMessage);
            }
        };
        image.src = src;

        return asset;
    }
    function isSpriteReady(asset) {
        return Boolean(
            asset &&
            asset.loaded &&
            asset.image.naturalWidth > 0 &&
            asset.image.naturalHeight > 0
        );
    }
    function hideSpriteOverlay(asset) {
        if (asset && asset.overlay) {
            asset.overlay.style.display = "none";
        }
    }
    function restartGifOverlay(asset) {
        if (!asset || !asset.overlay) return;
        const baseSrc = asset.image.src;
        asset.overlay.src = "";
        asset.overlay.src = baseSrc;
    }
//#endregion

//#region Audio System
    // ==========================================
    // AUDIO SYSTEM (BGM, PAUSE/GAMEOVER SYNC & AUTOPLAY UNLOCK)
    // ==========================================
    const AudioSystem = {
        bgmSrc: "../assets/audio/game_bgm.mp3",
        gameOverSrc: "",
        bgm: null,
        gameOverSfx: null,
        volume: 0.45,
        bgmVolume: 0.45,
        sfxVolume: 0.45,
        muted: false,
        bgmMuted: false,
        sfxMuted: false,
        unlocked: false,

        // Regular jump sound
        jumpSrc: "../assets/audio/Jump_SFX.mp3",
        jumpSfx: null,

        // Dedicated super jump sound
        superJumpSrc: "../assets/audio/SuperJump_SFX.mp3", // <-- update path/extension
        superJumpSfx: null,

        // 3 hit sounds: Hit_SFX2 is rarest
        hitSounds: [
            { src: "../assets/audio/Hit_SFX1.mp3", weight: 4.7, volumeScale: 2 },
            { src: "../assets/audio/Hit_SFX2.mp3", weight: 0.3, volumeScale: 1.2 }, // rarest
            { src: "../assets/audio/Hit_SFX3.mp3", weight: 4.7, volumeScale: 2 }
        ],
        hitAudioPool: [],

        // 3 lifeup sounds (equal weights)
        lifeUpSounds: [
            { src: "../assets/audio/LifeUp_SFX1.mp3", weight: 1, volumeScale: 1.0 },
            { src: "../assets/audio/LifeUp_SFX2.mp3", weight: 1, volumeScale: 1.0 },
            { src: "../assets/audio/LifeUp_SFX3.mp3", weight: 1, volumeScale: 1.0 }
        ],
        lifeUpAudioPool: [],

        createAudioClip(src, volumeScale = 1) {
            if (!src) return null;
            const audio = new Audio(src);
            audio.loop = false;
            audio.volume = this.muted || this.sfxMuted ? 0 : Math.min(1, this.sfxVolume * volumeScale);
            audio.preload = "auto";
            return audio;
        },

        init() {
            this.bgmVolume = this.volume;
            this.sfxVolume = this.volume;

            this.bgm = new Audio(this.bgmSrc);
            this.bgm.loop = true;
            this.bgm.volume = this.isBgmEffectivelyMuted() ? 0 : this.bgmVolume;
            this.bgm.preload = "auto";

            if (this.gameOverSrc) {
                this.gameOverSfx = this.createAudioClip(this.gameOverSrc, 1.1);
            }

            this.jumpSfx = this.createAudioClip(this.jumpSrc, 0.9);
            this.superJumpSfx = this.createAudioClip(this.superJumpSrc, 1.0);

            this.hitAudioPool = this.hitSounds.map((entry) => ({
                audio: this.createAudioClip(entry.src, entry.volumeScale ?? 1.1),
                weight: Math.max(0, entry.weight ?? 1),
                volumeScale: entry.volumeScale ?? 1.1
            }));

            this.lifeUpAudioPool = this.lifeUpSounds.map((entry) => ({
                audio: this.createAudioClip(entry.src, entry.volumeScale ?? 1.0),
                weight: Math.max(0, entry.weight ?? 1),
                volumeScale: entry.volumeScale ?? 1.0
            }));
        },

        pickWeightedEntry(pool) {
            const validEntries = pool.filter((item) => item && item.audio && item.weight > 0);
            if (validEntries.length === 0) return null;

            const totalWeight = validEntries.reduce((sum, item) => sum + item.weight, 0);
            let roll = Math.random() * totalWeight;

            for (const entry of validEntries) {
                roll -= entry.weight;
                if (roll <= 0) {
                    return entry;
                }
            }

            return validEntries[validEntries.length - 1];
        },
        playRandomFromPool(pool, fallbackScale = 1) {
            if (this.muted || this.sfxMuted) return;
            const chosen = this.pickWeightedEntry(pool);
            if (!chosen) return;
            this.playSfx(chosen.audio, chosen.volumeScale ?? fallbackScale);
        },
        playSfx(audioEl, volumeScale = 1) {
            if (!audioEl || this.muted || this.sfxMuted) return;
            audioEl.volume = Math.min(1, this.sfxVolume * volumeScale);
            audioEl.currentTime = 0;
            const playPromise = audioEl.play();
            if (playPromise && typeof playPromise.catch === "function") {
                playPromise.catch(() => {});
            }
        },
        playJump(volumeScale = 0.9) {
            this.playSfx(this.jumpSfx, volumeScale);
        },
        playSuperJump(volumeScale = 1.0) {
            this.playSfx(this.superJumpSfx, volumeScale);
        },
        playHurt() {
            this.playRandomFromPool(this.hitAudioPool, 1.1);
        },
        playLifeUp() {
            this.playRandomFromPool(this.lifeUpAudioPool, 1.0);
        },

        isBgmEffectivelyMuted() {
            return Boolean(this.muted || this.bgmMuted);
        },
        syncBgmAudio() {
            if (!this.bgm) return;
            const shouldMute = this.isBgmEffectivelyMuted();
            this.bgm.volume = shouldMute ? 0 : this.bgmVolume;

            if (shouldMute) {
                this.pauseBgm();
            } else if (this.unlocked && gameState === "playing") {
                this.playBgm();
            }
        },
        unlockAndSync(currentGameState) {
            this.unlocked = true;
            if (currentGameState === "playing") {
                this.playBgm();
            }
        },
        playBgm() {
            if (!this.bgm || this.isBgmEffectivelyMuted()) return;
            this.bgm.volume = this.bgmVolume;
            const playPromise = this.bgm.play();
            if (playPromise && typeof playPromise.catch === "function") {
                playPromise.catch(() => {});
            }
        },
        pauseBgm() {
            if (!this.bgm) return;
            this.bgm.pause();
        },
        stopAndRewindBgm() {
            if (!this.bgm) return;
            this.bgm.pause();
            this.bgm.currentTime = 0;
        },
        playGameOver() {
            this.stopAndRewindBgm();
            if (!this.gameOverSfx || this.muted || this.sfxMuted) return;
            this.gameOverSfx.volume = Math.min(1, this.sfxVolume * 1.1);
            this.gameOverSfx.currentTime = 0;
            const playPromise = this.gameOverSfx.play();
            if (playPromise && typeof playPromise.catch === "function") {
                playPromise.catch(() => {});
            }
        },
        setBgmMuted(muteState) {
            this.bgmMuted = Boolean(muteState);
            this.syncBgmAudio();
        },
        toggleBgmMute() {
            this.setBgmMuted(!this.bgmMuted);
        },
        setBgmVolume(newVol) {
            this.bgmVolume = Math.max(0, Math.min(1, Number(newVol) || 0));
            if (this.bgmVolume > 0 && this.bgmMuted) {
                this.bgmMuted = false;
            }
            this.syncBgmAudio();
        },
        setSfxVolume(newVol) {
            this.sfxVolume = Math.max(0, Math.min(1, Number(newVol) || 0));
            if (this.sfxVolume > 0 && this.sfxMuted) {
                this.sfxMuted = false;
            }
        },
        setMuted(muteState) {
            this.muted = Boolean(muteState);
            this.syncBgmAudio();
        },
        toggleMute() {
            this.setMuted(!this.muted);
        }
    };
    AudioSystem.init();
//#endregion

//#region Player System
    // ==========================================
    // PLAYER SYSTEM (STATE, INPUTS, PHYSICS & VISUALS)
    // ==========================================
    const PlayerSystem = {
        state: {
            // Hitbox dimensions (kept separate from visual sprite scale)
            x: 0,
            y: 0,
            width: 40,
            height: 20,
            facing: 1, // 1 = right, -1 = left

            // Visual sprite sizing
            spriteScale: 1.25,

            // Velocity
            velocityX: 0,
            velocityY: 0,

            // Initial Lives & food requirement
            maxLives: 3,
            foodToLives: 15,

            // Movement & jump tuning
            speed: 2.5,
            jumpPower: 10,
            smallJumpPower: 7.5,
            peakExtendThreshold: 4,
            peakGravityScale: 0.35,
            gravity: 0.2,
            grounded: false,
            canExtendJump: false,

            // Platform drop-through state
            dropThroughFrames: 10,
            dropThroughTimer: 0,
            ignorePlatform: null,

            // Super jump state
            superJumpPowerScale: Math.SQRT2,
            superJumpChargeMs: 0,
            superJumpChargeRequiredMs: 1500,
            superJumpHungerCost: 3,
            isSuperJumping: false,
            noHungerPromptTimerMs: 0,
            noHungerPromptDurationMs: 1100,

            // Knockback, stun, hitstop & post-hit invulnerability
            knockbackSpeedX: 2.5,
            knockbackHopY: -4.2,
            hurtStunDurationMs: 250,      // 0.25s movement lock on hit
            hurtStunTimerMs: 0,
            postHitInvulnDurationMs: 3000, // fraction of a second invulnerable after taking a hit
            invulnTimerMs: 0,
            hitstopDurationMs: 350,        // brief impact freeze-frame
            hitstopTimerMs: 0,

            // Fall-off-bounds coyote hop & delayed respawn state
            isFallingOutOfBounds: false,
            fallHopVelocityY: -7.5,       // upward coyote hop before dropping off-screen
            fallRespawnOffsetY: 160      // how far below cameraY + canvas.height before respawning
        },

        keys: {
            left: false,
            right: false,
            dropDown: false,
            dropDownPressed: false,
            jump: false,
            jumpPressed: false,
            jumpHeld: false,
            superJumpHeld: false,
            superJumpTriggered: false
        },

        jumpInputConfig: {
            doubleTapWindowMs: 250,
            doubleTapSuperJumpEnabled: true
        },

        lastJumpTapTime: 0,

        gizmoConfig: {
            enabled: true,
            boxWidth: 74,
            boxHeight: 34,
            gap: 8,
            offsetX: 12,
            offsetY: 72,
            pressFlashMs: 120,
            idleColor: "#22262e",
            pressedColor: "#22c55e",
            heldColor: "#f59e0b",
            borderColor: "#cbd5e1",
            textColor: "#ffffff"
        },

        gizmoRuntime: {
            jumpPressFlashUntil: 0,
            clickPressFlashUntil: 0,
            prevJumpActive: false,
            prevClickActive: false
        },

        assets: {
            idle: createSpriteAsset(
                "../assets/images/dante.gif",
                "spiderGifOverlay",
                "Spider sprite failed to load; using fallback box."
            ),
            walk: createSpriteAsset(
                "../assets/images/dante_walk.gif",
                "spiderWalkGifOverlay",
                "Spider walk sprite failed to load; using idle spider sprite."
            ),
            charge: createSpriteAsset(
                "../assets/images/dante_charge.gif",
                "spiderChargeGifOverlay",
                "Spider charge sprite failed to load; using idle spider sprite."
            ),
            invulnIdle: createSpriteAsset(
                "../assets/images/dante2.gif",
                "spiderInvulnGifOverlay",
                "Invulnerable idle sprite failed to load; falling back to idle sprite."
            ),
            invulnWalk: createSpriteAsset(
                "../assets/images/dante_walk2.gif",
                "spiderWalkInvulnGifOverlay",
                "Invulnerable walk sprite failed to load; falling back to walk sprite."
            ),
            invulnCharge: createSpriteAsset(
                "../assets/images/dante_charge2.gif",
                "spiderChargeInvulnGifOverlay",
                "Invulnerable charge sprite failed to load; falling back to charge sprite."
            ),
            wasGroundedCharging: false,
            wasWalking: false
        },

        getAllAssets() {
            return [
                this.assets.idle,
                this.assets.walk,
                this.assets.charge,
                this.assets.invulnIdle,
                this.assets.invulnWalk,
                this.assets.invulnCharge
            ];
        },

        //Player States
        isInvulnerable() {
            const p = this.state;
            return p.isSuperJumping || p.invulnTimerMs > 0;
        },
        isStunned() {
            return this.state.hurtStunTimerMs > 0;
        },
        tickDamageTimers(dt) {
            const p = this.state;
            const frameMs = dt * (1000 / 60);

            if (p.hurtStunTimerMs > 0) {
                p.hurtStunTimerMs = Math.max(0, p.hurtStunTimerMs - frameMs);
            }
            if (p.invulnTimerMs > 0) {
                p.invulnTimerMs = Math.max(0, p.invulnTimerMs - frameMs);
            }
            if (p.noHungerPromptTimerMs > 0) {
                p.noHungerPromptTimerMs = Math.max(0, p.noHungerPromptTimerMs - frameMs);
            }
        },
        triggerHitstop() {
            const p = this.state;
            p.hitstopTimerMs = p.hitstopDurationMs;
        },
        resetState(spawnX, spawnY) {
            const p = this.state;
            p.x = spawnX;
            p.y = spawnY;
            p.velocityX = 0;
            p.velocityY = 0;
            p.grounded = false;
            p.facing = 1;
            p.canExtendJump = false;
            p.dropThroughTimer = 0;
            p.ignorePlatform = null;
            p.superJumpChargeMs = 0;
            p.isSuperJumping = false;
            p.hurtStunTimerMs = 0;
            p.invulnTimerMs = 0;
            p.hitstopTimerMs = 0;
            p.isFallingOutOfBounds = false;
        },

        respawnOnPlatform(targetPlatform, fallbackX, fallbackY) {
            const p = this.state;

            if (targetPlatform) {
                const clampedX = Math.max(
                    0,
                    Math.min(gameWidth - p.width, targetPlatform.x + targetPlatform.width / 2 - p.width / 2)
                );
                p.x = clampedX;
                p.y = targetPlatform.y - p.height;
                p.grounded = true;
            } else {
                p.x = fallbackX;
                p.y = fallbackY;
                p.grounded = false;
            }

            p.velocityX = 0;
            p.velocityY = 0;
            p.canExtendJump = false;
            p.dropThroughTimer = 0;
            p.ignorePlatform = null;
            p.superJumpChargeMs = 0;
            p.isSuperJumping = false;
            p.hurtStunTimerMs = 0;
            p.hitstopTimerMs = 0;
            p.isFallingOutOfBounds = false;
            p.invulnTimerMs = p.postHitInvulnDurationMs;
        },
        handleObstacleHit(sourceX, onLoseLife, onGameOver) {
            const p = this.state;
            if (this.isInvulnerable()) return;

            // Cancel any active charge when taking a hit
            p.superJumpChargeMs = 0;
            p.canExtendJump = false;

            // Knockback away from the obstacle (or opposite facing if aligned)
            const playerCenterX = p.x + p.width / 2;
            let pushDir = playerCenterX < sourceX ? -1 : 1;
            if (Math.abs(playerCenterX - sourceX) < 1) {
                pushDir = -p.facing;
            }

            p.velocityX = pushDir * p.knockbackSpeedX;
            p.velocityY = p.knockbackHopY;
            p.grounded = false;
            p.facing = pushDir === -1 ? 1 : -1;

            // Apply 0.25s stun, post-hit invulnerability, and hitstop on life loss
            p.hurtStunTimerMs = p.hurtStunDurationMs;
            p.invulnTimerMs = p.postHitInvulnDurationMs;
            AudioSystem.playHurt();
            this.triggerHitstop();

            const remainingLives = onLoseLife();
            if (remainingLives <= 0) {
                p.hitstopTimerMs = 0;
                onGameOver();
            }
        },
        startFallOutOfBoundsSequence() {
            const p = this.state;
            p.isFallingOutOfBounds = true;
            p.grounded = false;
            p.canExtendJump = false;
            p.superJumpChargeMs = 0;
            p.isSuperJumping = false;
            p.hurtStunTimerMs = 0;
            p.dropThroughTimer = 0;
            p.ignorePlatform = null;
            p.velocityX = 0;
            p.velocityY = p.fallHopVelocityY; // coyote hop upward first
        },

        setLeftInput(active) {
            this.keys.left = Boolean(active);
        },
        setRightInput(active) {
            this.keys.right = Boolean(active);
        },
        setDropDownInput(active) {
            const isNowActive = Boolean(active);
            if (isNowActive && !this.keys.dropDown) {
                this.keys.dropDownPressed = true;
            }
            this.keys.dropDown = isNowActive;
        },
        setJumpInput(active) {
            const isNowActive = Boolean(active);

            if (isNowActive) {
                if (!this.keys.jumpHeld) {
                    const now = performance.now();
                    const isDoubleTap =
                        this.jumpInputConfig.doubleTapSuperJumpEnabled &&
                        (now - this.lastJumpTapTime) <= this.jumpInputConfig.doubleTapWindowMs;

                    this.keys.jumpPressed = true;
                    if (isDoubleTap) {
                        this.keys.superJumpTriggered = true;
                        this.lastJumpTapTime = 0;
                    } else {
                        this.lastJumpTapTime = now;
                    }
                }
                this.keys.jump = true;
                this.keys.jumpHeld = true;
            } else {
                this.keys.jump = false;
                this.keys.jumpHeld = false;
            }
        },
        setSuperJumpHoldInput(active) {
            this.keys.superJumpHeld = Boolean(active);
        },
        triggerSuperJumpButton() {
            this.keys.superJumpTriggered = true;
        },
        setDoubleTapSuperJump(enabled) {
            this.jumpInputConfig.doubleTapSuperJumpEnabled = Boolean(enabled);
        },

        clearAllInputs() {
            this.keys.left = false;
            this.keys.right = false;
            this.keys.dropDown = false;
            this.keys.dropDownPressed = false;
            this.keys.jump = false;
            this.keys.jumpPressed = false;
            this.keys.jumpHeld = false;
            this.keys.superJumpHeld = false;
            this.keys.superJumpTriggered = false;
            this.lastJumpTapTime = 0;
            this.gizmoRuntime.jumpPressFlashUntil = 0;
            this.gizmoRuntime.clickPressFlashUntil = 0;
        },

        consumeFrameEdgeInputs() {
            this.keys.jumpPressed = false;
            this.keys.dropDownPressed = false;
            this.keys.superJumpTriggered = false;
        },

        handleKeyInput(event, isDown) {
            const k = event.key.toLowerCase();

            if (k === "a" || k === "arrowleft") {
                this.setLeftInput(isDown);
            }
            if (k === "d" || k === "arrowright") {
                this.setRightInput(isDown);
            }
            if (k === "s" || k === "arrowdown") {
                this.setDropDownInput(isDown);
            }
            if (event.code === "Space" || k === "w" || k === "arrowup") {
                this.setJumpInput(isDown);
                if (event.code === "Space") {
                    event.preventDefault();
                }
            }
        },

        updateHorizontalMovement(dt) {
            const p = this.state;
            const hasEnoughHungerForSuperJump = TerrainSystem.foodProgress >= p.superJumpHungerCost;
            const isChargingSuperJump =
                this.keys.superJumpHeld &&
                !p.isSuperJumping &&
                hasEnoughHungerForSuperJump;

            // Lock horizontal control during 0.25s hit-stun while preserving knockback drift
            if (p.hurtStunTimerMs > 0) {
                p.x += p.velocityX * dt;
                p.x = Math.max(0, Math.min(gameWidth - p.width, p.x));
                return;
            }

            // Allow air-steering while charging; lock horizontal movement once grounded
            if (isChargingSuperJump && p.grounded) {
                p.velocityX = 0;
                return;
            }

            if (this.keys.left) {
                p.velocityX = -p.speed;
                p.facing = -1;
            } else if (this.keys.right) {
                p.velocityX = p.speed;
                p.facing = 1;
            } else {
                p.velocityX = 0;
            }

            p.x += p.velocityX * dt;
            p.x = Math.max(0, Math.min(gameWidth - p.width, p.x));
        },

        updateVerticalMotion(dt) {
            const p = this.state;
            const frameMs = dt * (1000 / 60);
            const hasEnoughHungerForSuperJump = TerrainSystem.foodProgress >= p.superJumpHungerCost;
            const isChargingSuperJump =
                this.keys.superJumpHeld &&
                !p.isSuperJumping &&
                hasEnoughHungerForSuperJump;

            // Charge Super Jump while Click is held (and player has >= 3 hunger); releasing early resets charge
            if (isChargingSuperJump) {
                p.superJumpChargeMs += frameMs;
                p.noHungerPromptTimerMs = 0;

                if (p.superJumpChargeMs >= p.superJumpChargeRequiredMs) {
                    if (TerrainSystem.consumeHunger(p.superJumpHungerCost)) {
                        p.superJumpChargeMs = 0;
                        p.isSuperJumping = true;
                        p.grounded = false;
                        p.canExtendJump = false;
                        p.ignorePlatform = null;
                        p.dropThroughTimer = 0;
                        p.velocityY = -(p.jumpPower * p.superJumpPowerScale);
                        AudioSystem.playSuperJump(1.0);
                    } else {
                        p.superJumpChargeMs = 0;
                    }
                }
            } else {
                p.superJumpChargeMs = 0;
                if (this.keys.superJumpHeld && !p.isSuperJumping && !hasEnoughHungerForSuperJump) {
                    p.noHungerPromptTimerMs = p.noHungerPromptDurationMs;
                }
            }

            // Regular jump (blocked while stunned, charging, or mid-super-jump)
            if (
                p.hurtStunTimerMs <= 0 &&
                !isChargingSuperJump &&
                !p.isSuperJumping &&
                this.keys.jumpPressed &&
                p.grounded
            ) {
                p.velocityY = -p.smallJumpPower;
                p.grounded = false;
                p.canExtendJump = true;
                p.ignorePlatform = null;
                p.dropThroughTimer = 0;
                AudioSystem.playJump();
            }

            // Stop peak extension if jump is released, charging starts, or player begins falling
            if (isChargingSuperJump || !this.keys.jumpHeld || p.velocityY > 0) {
                p.canExtendJump = false;
            }

            // Reduce gravity near peak while holding jump
            let effectiveGravity = p.gravity;
            if (
                !isChargingSuperJump &&
                !p.isSuperJumping &&
                this.keys.jumpHeld &&
                p.canExtendJump &&
                p.velocityY < 0 &&
                p.velocityY >= -p.peakExtendThreshold
            ) {
                effectiveGravity = p.gravity * p.peakGravityScale;
            }

            p.velocityY += effectiveGravity * dt;
            p.y += p.velocityY * dt;
        },

        resolvePlatformCollisions(prevBottom, platforms) {
            const pState = this.state;
            const isChargingSuperJump = this.keys.superJumpHeld && !pState.isSuperJumping;
            const wasGroundedOrAtSurface = pState.grounded;

            if (pState.dropThroughTimer > 0) {
                pState.dropThroughTimer = Math.max(0, pState.dropThroughTimer - 1);
                if (pState.dropThroughTimer === 0) {
                    pState.ignorePlatform = null;
                }
            }

            pState.grounded = false;

            if (pState.velocityY >= 0) {
                const bottom = pState.y + pState.height;
                for (const p of platforms) {
                    if (p.broken) {
                        continue;
                    }
                    if (p === pState.ignorePlatform && pState.dropThroughTimer > 0) {
                        continue;
                    }

                    const withinHorizontal =
                        pState.x < p.x + p.width &&
                        pState.x + pState.width > p.x;
                    const crossedTop = prevBottom <= p.y && bottom >= p.y;

                    if (withinHorizontal && crossedTop) {
                        // Only drop down on an intentional press while grounded/on-platform,
                        // and consume dropDownPressed so an early mid-air tap doesn't force a drop
                        if (!isChargingSuperJump && this.keys.dropDownPressed && wasGroundedOrAtSurface) {
                            this.keys.dropDownPressed = false;
                            pState.ignorePlatform = p;
                            pState.dropThroughTimer = pState.dropThroughFrames;
                            pState.canExtendJump = false;
                            break;
                        }
                        this.keys.dropDownPressed = false;

                        pState.y = p.y - p.height;
                        pState.velocityY = 0;
                        pState.grounded = true;
                        if (isChargingSuperJump) {
                            pState.velocityX = 0;
                        }
                        pState.isSuperJumping = false;
                        pState.ignorePlatform = null;
                        pState.dropThroughTimer = 0;

                        if (p.isFinishLine) {
                            completeStage();
                            break;
                        }

                        // Begin crumbling only after player makes contact
                        if (p.isCrumbly) {
                            p.crumbleTriggered = true;
                        }
                        break;
                    }
                }
            }
        },

        update(dt, platforms) {
            const p = this.state;
            if (p.isFallingOutOfBounds) {
                p.velocityX = 0;
                this.updateVerticalMotion(dt);
                return;
            }

            const prevBottom = p.y + p.height;
            this.updateHorizontalMovement(dt);
            this.updateVerticalMotion(dt);
            this.resolvePlatformCollisions(prevBottom, platforms);
        },

        checkEnemyCollision(enemies, onLoseLife, onGameOver) {
            const p = this.state;
            if (this.isInvulnerable()) return;

            for (let e of enemies) {
                const halfW = e.type === "lizard"
                    ? (e.hitboxWidth || TerrainSystem.lizardHitboxWidth || e.size) / 2
                    : e.size / 2;
                const halfH = e.type === "lizard"
                    ? (e.hitboxHeight || TerrainSystem.lizardHitboxHeight || e.size) / 2
                    : e.size / 2;

                if (
                    p.x < e.x + halfW &&
                    p.x + p.width > e.x - halfW &&
                    p.y < e.y + halfH &&
                    p.y + p.height > e.y - halfH
                ) {
                    this.handleObstacleHit(e.x, onLoseLife, onGameOver);
                    return;
                }
            }
        },

        checkRockCollision(rocks, onLoseLife, onGameOver) {
            const p = this.state;
            if (this.isInvulnerable()) return;

            let cx = p.x + p.width / 2;
            let cy = p.y + p.height / 2;
            let playerRadius = Math.max(p.width, p.height) / 2;

            for (let r of rocks) {
                if (Math.hypot(cx - r.x, cy - r.y) < playerRadius + r.size) {
                    this.handleObstacleHit(r.x, onLoseLife, onGameOver);
                    return;
                }
            }
        },

        selectActiveAsset() {
            const p = this.state;
            const invuln = this.isInvulnerable();
            const hasEnoughHungerForSuperJump = TerrainSystem.foodProgress >= p.superJumpHungerCost;
            const isGroundedCharging =
                this.keys.superJumpHeld &&
                !p.isSuperJumping &&
                p.grounded &&
                hasEnoughHungerForSuperJump;

            if (isGroundedCharging) {
                if (invuln && isSpriteReady(this.assets.invulnCharge)) {
                    return this.assets.invulnCharge;
                }
                if (isSpriteReady(this.assets.charge)) {
                    return this.assets.charge;
                }
            }

            const isMoving = !isGroundedCharging && (this.keys.left || this.keys.right || Math.abs(p.velocityX) > 0);
            if (isMoving) {
                if (invuln && isSpriteReady(this.assets.invulnWalk)) {
                    return this.assets.invulnWalk;
                }
                if (isSpriteReady(this.assets.walk)) {
                    return this.assets.walk;
                }
            }

            if (invuln && isSpriteReady(this.assets.invulnIdle)) {
                return this.assets.invulnIdle;
            }
            if (isSpriteReady(this.assets.idle)) {
                return this.assets.idle;
            }
            return null;
        },
        drawSprite(context, cameraY, isGameOver) {
            const p = this.state;
            const activeAsset = !isGameOver ? this.selectActiveAsset() : null;

            for (const asset of this.getAllAssets()) {
                if (asset !== activeAsset) {
                    hideSpriteOverlay(asset);
                }
            }

            if (isGameOver || !activeAsset) {
                this.assets.lastActiveAsset = null;
                if (isGameOver) return;
                context.fillStyle = "#2b6cff";
                context.fillRect(gameLeft + p.x, p.y - cameraY, p.width, p.height);
                return;
            }

            if (activeAsset !== this.assets.lastActiveAsset) {
                restartGifOverlay(activeAsset);
                this.assets.lastActiveAsset = activeAsset;
            }

            if (activeAsset.overlay) {
                const hitboxCenterX = gameLeft + p.x + p.width / 2;
                const hitboxBottomY = p.y - cameraY + p.height;
                const aspectRatio = activeAsset.image.naturalWidth / activeAsset.image.naturalHeight;
                const drawHeight = p.height * p.spriteScale;
                const drawWidth = drawHeight * aspectRatio;
                const drawTopY = hitboxBottomY - drawHeight;
                const drawLeftX = hitboxCenterX - drawWidth / 2;
                const canvasRect = canvas.getBoundingClientRect();

                const laneLeftScreenX = canvasRect.left + gameLeft;
                const laneRightScreenX = laneLeftScreenX + gameWidth;
                const spriteLeftScreenX = canvasRect.left + drawLeftX;
                const spriteRightScreenX = spriteLeftScreenX + drawWidth;

                const clipLeft = Math.max(0, laneLeftScreenX - spriteLeftScreenX);
                const clipRight = Math.max(0, spriteRightScreenX - laneRightScreenX);
                const invulnGlow = this.isInvulnerable()
                    ? "drop-shadow(0 0 6px rgba(255, 215, 0, 0.95)) drop-shadow(0 0 12px rgba(250, 204, 21, 0.75))"
                    : "none";

                activeAsset.overlay.style.display = "block";
                activeAsset.overlay.style.left = `${spriteLeftScreenX}px`;
                activeAsset.overlay.style.top = `${canvasRect.top + drawTopY}px`;
                activeAsset.overlay.style.width = `${drawWidth}px`;
                activeAsset.overlay.style.height = `${drawHeight}px`;
                activeAsset.overlay.style.transform = p.facing === -1 ? "scaleX(-1)" : "scaleX(1)";
                activeAsset.overlay.style.clipPath = `inset(0px ${clipRight}px 0px ${clipLeft}px)`;
            } else {
                context.fillStyle = "#2b6cff";
                context.fillRect(gameLeft + p.x, p.y - cameraY, p.width, p.height);
            }
        },

        drawStatusIndicators(context, cameraY) {
            const p = this.state;
            if (p.superJumpChargeMs > 0) {
                const chargeRatio = Math.min(1, p.superJumpChargeMs / p.superJumpChargeRequiredMs);
                const barWidth = p.width;
                const barHeight = 6;
                const barX = gameLeft + p.x;
                const barY = p.y - cameraY - 12;

                context.fillStyle = "#222";
                context.fillRect(barX, barY, barWidth, barHeight);
                context.fillStyle = "#22c55e";
                context.fillRect(barX, barY, barWidth * chargeRatio, barHeight);
            }
            if (p.noHungerPromptTimerMs > 0) {
                const promptX = gameLeft + p.x + p.width / 2;
                const promptY = p.y - cameraY - 18;

                context.save();
                context.font = "bold 13px 'Courier New', Courier, monospace";
                context.textAlign = "center";
                context.textBaseline = "bottom";

                context.lineWidth = 3;
                context.strokeStyle = "rgba(15, 23, 42, 0.9)";
                context.strokeText("I'm too hungry to do a Super Jump", promptX, promptY);

                context.fillStyle = "#fde047";
                context.fillText("I'm too hungry to do a Super Jump", promptX, promptY);
                context.restore();
            }
        },

        getGizmoBoxColor(boxKey) {
            const now = performance.now();
            const g = this.gizmoConfig;
            const r = this.gizmoRuntime;

            if (boxKey === "jump") {
                const isJumpActive = Boolean(this.keys.jumpHeld || this.keys.jump);
                if ((isJumpActive && !r.prevJumpActive) || this.keys.jumpPressed || this.keys.superJumpTriggered) {
                    r.jumpPressFlashUntil = now + g.pressFlashMs;
                }
                r.prevJumpActive = isJumpActive;

                if (now < r.jumpPressFlashUntil) return g.pressedColor;
                if (isJumpActive) return g.heldColor;
                return g.idleColor;
            }

            if (boxKey === "click") {
                if (this.keys.superJumpTriggered) return g.pressedColor;
                if (this.keys.superJumpHeld) return g.heldColor;
                return g.idleColor;
            }

            if (boxKey === "left" && this.keys.left) return g.pressedColor;
            if (boxKey === "right" && this.keys.right) return g.pressedColor;
            if (boxKey === "down" && this.keys.dropDown) return g.pressedColor;

            return g.idleColor;
        },

        drawGizmos(context) {
            const g = this.gizmoConfig;
            if (!g.enabled) return;

            const boxes = [
                { id: "jump",  label: "Jump"  },
                { id: "left",  label: "Left"  },
                { id: "right", label: "Right" },
                { id: "down",  label: "Down"  },
                { id: "click", label: "Click" }
            ];

            const startX = gameLeft + g.offsetX;
            const startY = g.offsetY;

            context.save();
            context.font = "bold 13px 'Courier New', Courier, monospace";
            context.textAlign = "center";
            context.textBaseline = "middle";

            boxes.forEach((box, index) => {
                const x = startX + index * (g.boxWidth + g.gap);
                const y = startY;
                const fillColor = this.getGizmoBoxColor(box.id);

                context.fillStyle = fillColor;
                context.fillRect(x, y, g.boxWidth, g.boxHeight);

                context.lineWidth = 2;
                context.strokeStyle = g.borderColor;
                context.strokeRect(x, y, g.boxWidth, g.boxHeight);

                context.fillStyle = g.textColor;
                context.fillText(box.label, x + g.boxWidth / 2, y + g.boxHeight / 2);
            });

            context.restore();
        }
    };
//#endregion

//#region Terrain and World System
    // ==========================================
    // TERRAIN & WORLD SYSTEM (PLATFORMS, COINS, ENEMIES & RAINDROPS)
    // ==========================================
    const ChallengeSystem = {
        active: null,
        elapsedSec: 0,
        hitsTaken: 0,

        pool: [
            {
                id: "collect_all_food",
                label: () => `Collect all ${StageConfig.targetFoods} foods before the finish`,
                evaluate: (ctx) => ctx.foodsCollected >= StageConfig.targetFoods,
                progressText: (ctx) => `${ctx.foodsCollected}/${StageConfig.targetFoods} foods`
            },
            {
                id: "time_limit",
                label: (ch) => `Reach the finish within ${ch.timeLimitSec}s`,
                timeLimitSec: 180,
                evaluate: (ctx, ch) => ctx.elapsedSec <= ch.timeLimitSec,
                progressText: (ctx, ch) => `${ctx.elapsedSec.toFixed(1)}s / ${ch.timeLimitSec}s`
            },
            {
                id: "max_hits",
                label: (ch) => `Take at most ${ch.maxHits} hit${ch.maxHits === 1 ? "" : "s"}`,
                maxHits: 3,
                evaluate: (ctx, ch) => ctx.hitsTaken <= ch.maxHits,
                progressText: (ctx, ch) => `${ctx.hitsTaken}/${ch.maxHits} hits`
            }
        ],

        rollForPageLoad() {
            const chosen = this.pool[Math.floor(Math.random() * this.pool.length)];
            this.active = { ...chosen };
            this.resetRunCounters();
        },

        resetRunCounters() {
            this.elapsedSec = 0;
            this.hitsTaken = 0;
        },

        recordHit() {
            this.hitsTaken++;
        },

        update(dt, isPlaying) {
            if (isPlaying) {
                this.elapsedSec += dt / 60;
            }
        },

        getContext() {
            return {
                foodsCollected: TerrainSystem.foodCount,
                elapsedSec: this.elapsedSec,
                hitsTaken: this.hitsTaken
            };
        },

        getTitle() {
            if (!this.active) return "";
            return typeof this.active.label === "function"
                ? this.active.label(this.active)
                : this.active.label;
        },

        getStatusText() {
            if (!this.active) return "";
            const ctx = this.getContext();
            const passed = this.active.evaluate(ctx, this.active);
            const prog = this.active.progressText ? ` (${this.active.progressText(ctx, this.active)})` : "";
            return passed ? `Challenge: ON TRACK${prog}` : `Challenge: MISSED${prog}`;
        },

        isCompleted() {
            if (!this.active) return false;
            return Boolean(this.active.evaluate(this.getContext(), this.active));
        }
    };

    ChallengeSystem.rollForPageLoad();
    const StageConfig = {
        targetFoods: 45,
        finishPlatformSpawned: false,
        finishPlatform: null,
        totalFoodsSpawned: 0
    };
    const TerrainSystem = {
        platforms: [],
        enemies: [],
        rocks: [],
        foods: [],

        cameraY: 0,
        startY: 0,
        rockTimer: 0,

        foodCount: 0,
        bestFoods: <?= (int)$high_score ?>,
        score: 0,
        bestScore: <?= (int)$height_reached ?>,
        newRecord: false,
        foodProgress: 0,
        lives: 3,

        platformWidth: 120,
        platformHeight: 20,
        minPlatformStepY: 65,
        maxPlatformStepY: 115,         // comfortably below max regular jump height (~140-165px)
        maxPlatformStepX: 240,

        crumblyChance: 0.28,           // chance a generated platform is crumbly (after initial safe platform)
        crumblyBreakDurationMs: 1500,  // time from first player contact until platform breaks
        crumblyMaxShakePx: 6,          // max horizontal shake offset right before breaking

        enemyMinVerticalSpacing: 300,
        flyWanderRadius: 100,
        flySpeed: 1,
        flyAcceleration: 0.08,
        flyRetargetMinMs: 1500,
        flyRetargetMaxMs: 3000,

        flyVisualScale: 1.35,
        flyXSprite: null,
        flyYSprite: null,

        spiderWanderRadius: 115,
        spiderSpeed: 1.55,
        spiderReturnSpeed: 1.25,
        spiderAcceleration: 0.1,
        spiderReactionDelayMs: 2000,
        spiderBoxWidth: 340,
        spiderBoxHeight: 240,
        spiderVisualScale: 1.3,
        spiderSprite: null,

        spiderWebVisualScale: 3,
        spiderWebSprite: null,

        lizardSightRangeX: 900,
        lizardSightToleranceY: 24,
        lizardFlashHeightScale: 1.35, // width/thickness of the flash band relative to lizard height
        lizardFlashAlpha: 0.35,       // semi-transparent warning fill
        lizardFlashDurationMs: 750,
        lizardMoveSpeed: 7.5,
        lizardPeekOffsetPx: -45, // how far the head pokes into the 600px lane while waiting
        lizardSprite: null,
        
        lizardVisualScale: 3,
        lizardHitboxWidth: 72,   // horizontal collision width (increase to make wider)
        lizardHitboxHeight: 72,  // vertical collision height (increase to make taller)
        lizardSprite: null,

        foodChance: 0.6,
        foodRadius: 10,
        foodVisualScale: 1.5,
        
        lastPlayerY: null,
        maxClimbPx: 0,
        waterY: 0,
        waterRiseSpeed: 0.55,           // world pixels per 60fps frame (smaller y = higher up)
        waterClimbRiseFactor: 0.50,     // water also rises by 75% of upward height gained by spider
        waterVisualOffsetY: -40,          // subtle wave/bob offset if desired
        respawnEscapeMarginY: 110,      // minimum vertical space above rising water so spider has room to escape

        waterScrollX: 0,
        waterScrollSpeedX: 45,        // rightward horizontal scroll speed in px/sec
        waterBobAmpPx: 3,             // subtle vertical wave bob for the whole water sheet
        waterBobSpeed: 3.0,
        
        raindropSprite: (() => {
            const img = new Image();
            const spriteObj = { image: img, loaded: false, scale: 3 };
            img.onload = function() { spriteObj.loaded = true; };
            img.onerror = function() {
                spriteObj.loaded = false;
                console.warn("Raindrop sprite failed to load; using fallback circle.");
            };
            img.src = "../assets/images/drop.png";
            return spriteObj;
        })(),

        backgroundSprite: (() => {
            const img = new Image();
            const spriteObj = { image: img, loaded: false, scale: 1 };
            img.onload = function() { spriteObj.loaded = true; };
            img.onerror = function() {
                spriteObj.loaded = false;
                console.warn("Background sprite failed to load; using fallback color.");
            };
            img.src = "../assets/images/background.png";
            return spriteObj;
        })(),

        waterSprite: (() => {
            const img = new Image();
            const spriteObj = { image: img, loaded: false, scale: 1 };
            img.onload = function() { spriteObj.loaded = true; };
            img.onerror = function() {
                spriteObj.loaded = false;
                console.warn("Water sprite failed to load; using fallback water.");
            };
            img.src = "../assets/images/water.png";
            return spriteObj;
        })(),
        
        createSpriteAsset(src, fallbackMessage, scale = 1) {
            const img = new Image();
            const spriteObj = { image: img, loaded: false, scale };
            img.onload = function() { spriteObj.loaded = true; };
            img.onerror = function() {
                spriteObj.loaded = false;
                if (fallbackMessage) {
                    console.warn(fallbackMessage);
                }
            };
            img.src = src;
            return spriteObj;
        },
        pickVariantIndex(variants) {
            if (!Array.isArray(variants) || variants.length === 0) return 0;
            return Math.floor(Math.random() * variants.length);
        },
        
        platformSprite: null,
        crumblySprites: [],
        foodSprites: [],

        initSprites() {
            this.platformSprite = this.createSpriteAsset(
                "../assets/images/platform.png",
                "Normal platform sprite failed to load; using fallback rectangle.",
                1
            );
            this.crumblySprites = [
                this.createSpriteAsset(
                    "../assets/images/crackedplatform1.png",
                    "Crumbly platform variant 1 failed to load; using fallback.",
                    1
                ),
                this.createSpriteAsset(
                    "../assets/images/crackedplatform2.png",
                    "Crumbly platform variant 2 failed to load; using fallback.",
                    1
                )
            ];
            this.foodSprites = [
                this.createSpriteAsset(
                    "../assets/images/fruit1.png",
                    "Food variant 1 failed to load; using fallback circle.",
                    this.foodVisualScale
                ),
                this.createSpriteAsset(
                    "../assets/images/fruit2.png",
                    "Food variant 2 failed to load; using fallback circle.",
                    this.foodVisualScale
                ),
                this.createSpriteAsset(
                    "../assets/images/fruit3.png",
                    "Food variant 3 failed to load; using fallback circle.",
                    this.foodVisualScale
                )
            ];
            this.flyXSprite = this.createSpriteAsset(
                "../assets/images/flyX.png",
                "Fly X sprite failed to load; using fallback.",
                this.flyVisualScale
            );
            this.flyYSprite = this.createSpriteAsset(
                "../assets/images/flyY.png",
                "Fly Y sprite failed to load; using fallback.",
                this.flyVisualScale
            );
            this.spiderSprite = this.createSpriteAsset(
                "../assets/images/spiderEnemy.png",
                "Spider enemy sprite failed to load; using fallback box.",
                this.spiderVisualScale
            );
            this.spiderWebSprite = this.createSpriteAsset(
                "../assets/images/spiderWeb.png",
                "Spider web sprite failed to load; skipping web sprite.",
                this.spiderWebVisualScale
            );
            this.lizardSprite = this.createSpriteAsset(
                "../assets/images/Lizard.png",
                "Lizard enemy sprite failed to load; using fallback box.",
                this.lizardVisualScale
            );
        },

        resetRunState(playerState, fullReset) {
            StageConfig.finishPlatformSpawned = false;
            StageConfig.finishPlatform = null;
            StageConfig.totalFoodsSpawned = 0;
            ChallengeSystem.resetRunCounters();

            this.platforms = [];
            this.enemies = [];
            this.rocks = [];
            this.foods = [];
            this.rockTimer = 0;

            this.startY = playerState.y;
            this.lastPlayerY = playerState.y;
            this.maxClimbPx = 0;
            this.cameraY = playerState.y - canvas.height / 2;

            if (fullReset) {
                this.lives = playerState.maxLives;
                this.score = 0;
                this.foodCount = 0;
                this.foodProgress = 0;
                this.newRecord = false;
            }

            this.startY = playerState.y;
            this.cameraY = playerState.y - canvas.height / 2;
            // Start rising water below the bottom of the initial screen
            this.waterY = this.cameraY + canvas.height + 140;

            this.createInitialPlatforms();
            this.generateInitialEnemies();
        },
        createFood(platform) {
            this.foods.push({
                x: platform.x + platform.width / 2,
                y: platform.y - this.foodRadius - 3,
                variantIndex: this.pickVariantIndex(this.foodSprites)
            });
        },
        consumeHunger(amount) {
            if (this.foodProgress < amount) return false;
            this.foodProgress -= amount;
            return true;
        },
        convertFullHungerToLife(playerState) {
            const foodRequired = playerState.foodToLives || 15;
            const maxLivesCap = playerState.maxLives || 3;

            if (this.foodProgress < foodRequired) return false;
            if (this.lives >= maxLivesCap) return false;

            this.foodProgress -= foodRequired;
            this.lives = Math.min(maxLivesCap, this.lives + 1);
            AudioSystem.playLifeUp();
            return true;
        },
        updateFoods(playerState) {
            const foodRequired = playerState.foodToLives || 15;

            this.foods = this.foods.filter((f) => {
                let nx = Math.max(playerState.x, Math.min(f.x, playerState.x + playerState.width));
                let ny = Math.max(playerState.y, Math.min(f.y, playerState.y + playerState.height));

                if (Math.hypot(f.x - nx, f.y - ny) < this.foodRadius) {
                    this.foodCount++;
                    if (this.foodCount > this.bestFoods) {
                        this.bestFoods = this.foodCount;
                        this.newRecord = true;
                        try {
                            localStorage.setItem("platformBestCoins", String(this.bestFoods));
                        } catch (e) {}
                    }

                    // Fill hunger bar up to full; player presses Right-Click when full to convert into +1 life
                    if (this.foodProgress < foodRequired) {
                        this.foodProgress = Math.min(foodRequired, this.foodProgress + 1);
                    }

                    return false;
                }
                return f.y - this.foodRadius < this.waterY;
            });
        },

        createPlatformObject(x, y, forceNormal = false) {
            const isCrumbly = !forceNormal && Math.random() < this.crumblyChance;
            const crumblyVariantIndex = isCrumbly
                ? this.pickVariantIndex(this.crumblySprites)
                : -1;

            return {
                x: x,
                y: y,
                width: this.platformWidth,
                height: this.platformHeight,
                type: isCrumbly ? "crumbly" : "normal",
                isCrumbly: isCrumbly,
                crumblyVariantIndex: crumblyVariantIndex,
                crumbleTriggered: false,
                crumbleElapsedMs: 0,
                broken: false
            };
        },
        createInitialPlatforms() {
            this.platforms = [
                this.createPlatformObject(gameWidth / 2 - 60, canvas.height - 100, true)
            ];
            for (let i = 1; i < 10; i++) {
                let p = this.createPlatformObject(
                    Math.random() * (gameWidth - this.platformWidth),
                    canvas.height - 100 - i * 100,
                    i === 1 // keep second platform safe/normal too
                );

                this.platforms.push(p);
                if (Math.random() < this.foodChance) {
                    this.createFood(p);
                }
            }
        },
        generatePlatforms() {
            let highest = this.platforms.reduce((a, b) => (a.y < b.y ? a : b));

            while (highest.y > this.cameraY - 200) {
                if (StageConfig.finishPlatformSpawned) {
                    break;
                }

                const stepY =
                    this.minPlatformStepY +
                    Math.random() * (this.maxPlatformStepY - this.minPlatformStepY);
                const nextY = highest.y - stepY;

                const prevCenterX = highest.x + highest.width / 2;
                const minAllowedX = Math.max(
                    0,
                    Math.min(gameWidth - this.platformWidth, prevCenterX - this.maxPlatformStepX - this.platformWidth / 2)
                );
                const maxAllowedX = Math.max(
                    minAllowedX,
                    Math.min(gameWidth - this.platformWidth, prevCenterX + this.maxPlatformStepX - this.platformWidth / 2)
                );

                const nextX = minAllowedX + Math.random() * (maxAllowedX - minAllowedX);
                highest = this.createPlatformObject(nextX, nextY, false);
                this.platforms.push(highest);

                if (StageConfig.totalFoodsSpawned < StageConfig.targetFoods) {
                    if (Math.random() < this.foodChance) {
                        this.createFood(highest);
                        StageConfig.totalFoodsSpawned++;
                    }
                } else if (!StageConfig.finishPlatformSpawned) {
                    // Place the Finish Line platform one step above the final food platform
                    const finishY = highest.y - (this.minPlatformStepY + 25);
                    const finishX = gameWidth / 2 - (this.platformWidth * 1.3) / 2;
                    const finishPlat = this.createPlatformObject(finishX, finishY, true);
                    finishPlat.width = this.platformWidth * 1.3;
                    finishPlat.isFinishLine = true;
                    this.platforms.push(finishPlat);
                    StageConfig.finishPlatform = finishPlat;
                    StageConfig.finishPlatformSpawned = true;
                }
            }
        },
        findBottomScreenPlatform() {
            const screenTop = this.cameraY;
            const screenBottom = this.cameraY + canvas.height;
            const safeMaxY = this.waterY - this.respawnEscapeMarginY;

            // 1. Best case: visible on-screen platforms with plenty of escape room above the water
            const safeOnScreen = this.platforms.filter(
                (p) =>
                    p.y + p.height >= screenTop &&
                    p.y <= screenBottom &&
                    p.y <= safeMaxY
            );
            if (safeOnScreen.length > 0) {
                return safeOnScreen.reduce((lowest, current) =>
                    current.y > lowest.y ? current : lowest
                );
            }

            // 2. Fallback: any surviving platform above the water with safe clearance
            const safeAny = this.platforms.filter((p) => p.y <= safeMaxY);
            if (safeAny.length > 0) {
                return safeAny.reduce((lowest, current) =>
                    current.y > lowest.y ? current : lowest
                );
            }

            // 3. Last resort: any platform still above the water surface
            const aboveWater = this.platforms.filter((p) => p.y < this.waterY - 24);
            if (aboveWater.length > 0) {
                return aboveWater.reduce((lowest, current) =>
                    current.y > lowest.y ? current : lowest
                );
            }

            return null;
        },
        updatePlatforms(dt, playerState) {
            const frameMs = dt * (1000 / 60);

            for (const p of this.platforms) {
                if (!p.isCrumbly || !p.crumbleTriggered || p.broken) {
                    continue;
                }

                p.crumbleElapsedMs += frameMs;
                if (p.crumbleElapsedMs >= this.crumblyBreakDurationMs) {
                    p.broken = true;

                    // If the player was standing on this exact platform, let them fall cleanly
                    const standingOnPlatform =
                        playerState &&
                        playerState.grounded &&
                        Math.abs((playerState.y + playerState.height) - p.y) <= 1 &&
                        playerState.x < p.x + p.width &&
                        playerState.x + playerState.width > p.x;

                    if (standingOnPlatform) {
                        playerState.grounded = false;
                    }
                }
            }

            // Keep platforms cached until touched by rising water, and drop broken ones
            this.platforms = this.platforms.filter((p) => {
                return !p.broken && p.y < this.waterY;
            });
        },
        
        drawSingleEnemy(context, e) {
            const screenX = gameLeft + e.x;
            const screenY = e.y - this.cameraY;

            if (e.type === "fly") {
                const vx = e.vx || 0;
                const vy = e.vy || 0;
                const moveThreshold = 0.08;

                if (Math.abs(vx) > moveThreshold || Math.abs(vy) > moveThreshold) {
                    if (Math.abs(vx) >= Math.abs(vy)) {
                        e.animAxis = "x";
                        e.flipX = vx > 0 ? -1 : 1;
                        e.flipY = 1;
                    } else {
                        e.animAxis = "y";
                        e.flipX = 1;
                        e.flipY = vy > 0 ? 1 : -1;
                    }
                }

                const activeFlySprite = e.animAxis === "y" ? this.flyYSprite : this.flyXSprite;
                const spriteReady =
                    activeFlySprite &&
                    activeFlySprite.loaded &&
                    activeFlySprite.image.naturalWidth > 0 &&
                    activeFlySprite.image.naturalHeight > 0;

                if (spriteReady) {
                    const aspectRatio = activeFlySprite.image.naturalWidth / activeFlySprite.image.naturalHeight;
                    const drawSize = e.size * (activeFlySprite.scale || this.flyVisualScale);
                    const drawWidth = aspectRatio >= 1 ? drawSize : drawSize * aspectRatio;
                    const drawHeight = aspectRatio >= 1 ? drawSize / aspectRatio : drawSize;

                    context.save();
                    context.translate(screenX, screenY);
                    context.scale(e.flipX, e.flipY);
                    context.imageSmoothingEnabled = false;
                    context.drawImage(
                        activeFlySprite.image,
                        -drawWidth / 2,
                        -drawHeight / 2,
                        drawWidth,
                        drawHeight
                    );
                    context.restore();
                } else {
                    context.fillStyle = "#ef4444";
                    context.fillRect(screenX - e.size / 2, screenY - e.size / 2, e.size, e.size);
                }
            } else if (e.type === "spider") {
                if (e.box) {
                    context.save();
                    context.strokeStyle = e.alerted
                        ? "rgba(250, 204, 21, 0.55)"
                        : "rgba(255, 255, 255, 0.2)";
                    context.lineWidth = 1.5;
                    context.setLineDash([5, 4]);
                    context.strokeRect(
                        gameLeft + e.box.x,
                        e.box.y - this.cameraY,
                        e.box.width,
                        e.box.height
                    );
                    context.restore();
                }

                const spAsset = this.spiderSprite;
                const spReady =
                    spAsset &&
                    spAsset.loaded &&
                    spAsset.image.naturalWidth > 0 &&
                    spAsset.image.naturalHeight > 0;

                if (spReady) {
                    const aspectRatio = spAsset.image.naturalWidth / spAsset.image.naturalHeight;
                    const drawSize = e.size * (spAsset.scale || this.spiderVisualScale);
                    const drawWidth = aspectRatio >= 1 ? drawSize : drawSize * aspectRatio;
                    const drawHeight = aspectRatio >= 1 ? drawSize / aspectRatio : drawSize;
                    const faceSign = (e.vx || 0) < -0.05 ? -1 : 1;

                    context.save();
                    context.translate(screenX, screenY);
                    context.scale(faceSign, 1);
                    context.imageSmoothingEnabled = false;
                    context.drawImage(
                        spAsset.image,
                        -drawWidth / 2,
                        -drawHeight / 2,
                        drawWidth,
                        drawHeight
                    );
                    context.restore();
                } else {
                    context.fillStyle = e.alerted ? "#f97316" : "#991b1b";
                    context.fillRect(screenX - e.size / 2, screenY - e.size / 2, e.size, e.size);
                }

                if (e.alerted && !e.hasReacted && this.spiderReactionDelayMs > 0) {
                    const progress = Math.min(1, (e.reactionTimerMs || 0) / this.spiderReactionDelayMs);
                    const barW = 34;
                    const barH = 5;
                    const barX = screenX - barW / 2;
                    const barY = screenY - e.size / 2 - 12;

                    context.fillStyle = "rgba(15, 23, 42, 0.85)";
                    context.fillRect(barX, barY, barW, barH);
                    context.fillStyle = "#facc15";
                    context.fillRect(barX, barY, barW * progress, barH);
                }
            } else if (e.type === "lizard") {
                const lzAsset = this.lizardSprite;
                const lzReady =
                    lzAsset &&
                    lzAsset.loaded &&
                    lzAsset.image.naturalWidth > 0 &&
                    lzAsset.image.naturalHeight > 0;

                // Lizard.png faces left by default; flip horizontally when moving/facing right (dir === 1)
                const faceSign = (e.dir || 1) === 1 ? -1 : 1;
                const lizardDrawHeight = lzReady
                    ? e.size * (lzAsset.scale || this.lizardVisualScale)
                    : e.size * 0.8;

                // Wide, semi-transparent red flash band across the lizard's forward lane
                if (e.lizardState === "flashing") {
                    const bandStartX = gameLeft;
                    const bandEndX = gameLeft + gameWidth;
                    const bandWidth = bandEndX - bandStartX;
                    const bandHeight = lizardDrawHeight * (this.lizardFlashHeightScale || 1.35);
                    const bandTopY = screenY - bandHeight / 2;

                    if (bandWidth > 0) {
                        context.save();
                        context.fillStyle = `rgba(239, 68, 68, ${this.lizardFlashAlpha ?? 0.35})`;
                        context.fillRect(bandStartX, bandTopY, bandWidth, bandHeight);
                        context.restore();
                    }
                }

                if (lzReady) {
                    const aspectRatio = lzAsset.image.naturalWidth / lzAsset.image.naturalHeight;
                    const drawHeight = lizardDrawHeight;
                    const drawWidth = drawHeight * aspectRatio;

                    context.save();
                    context.translate(screenX, screenY);
                    context.scale(faceSign, 1);
                    context.imageSmoothingEnabled = false;
                    context.drawImage(
                        lzAsset.image,
                        -drawWidth / 2,
                        -drawHeight / 2,
                        drawWidth,
                        drawHeight
                    );
                    context.restore();
                } else {
                    context.fillStyle = e.lizardState === "flashing" ? "#f97316" : "#22c55e";
                    context.fillRect(
                        screenX - (e.size * 1.3) / 2,
                        screenY - (e.size * 0.8) / 2,
                        e.size * 1.3,
                        e.size * 0.8
                    );
                }
            } else {
                context.fillStyle = "red";
                context.fillRect(screenX - e.size / 2, screenY - e.size / 2, e.size, e.size);
            }
        },
        isPlayerInEnemyBox(enemy, playerState) {
            if (!playerState || !enemy || !enemy.box) return false;
            const b = enemy.box;

            return (
                playerState.x < b.x + b.width &&
                playerState.x + playerState.width > b.x &&
                playerState.y < b.y + b.height &&
                playerState.y + playerState.height > b.y
            );
        },
        pickRandomPointInRadius(anchorX, anchorY, radius) {
            const angle = Math.random() * Math.PI * 2;
            const dist = Math.sqrt(Math.random()) * radius;
            const rawX = anchorX + Math.cos(angle) * dist;
            const rawY = anchorY + Math.sin(angle) * dist;

            return {
                x: Math.max(24, Math.min(gameWidth - 24, rawX)),
                y: rawY
            };
        },
        initRadiusMotion(enemy, anchorX, anchorY, radius, speed, accel, retargetMinMs, retargetMaxMs) {
            enemy.anchorX = anchorX;
            enemy.anchorY = anchorY;
            enemy.wanderRadius = radius;
            enemy.vx = 0;
            enemy.vy = 0;
            enemy.moveSpeed = speed;
            enemy.accel = accel;
            enemy.retargetMinMs = retargetMinMs;
            enemy.retargetMaxMs = retargetMaxMs;
            enemy.retargetTimerMs = retargetMinMs + Math.random() * (retargetMaxMs - retargetMinMs);

            const initialTarget = this.pickRandomPointInRadius(anchorX, anchorY, radius);
            enemy.targetX = initialTarget.x;
            enemy.targetY = initialTarget.y;
        },
        stepRadiusMotion(dt, enemy, desiredX, desiredY, maxSpeed, accel) {
            const frameMs = dt * (1000 / 60);
            const dxFromAnchor = enemy.x - enemy.anchorX;
            const dyFromAnchor = enemy.y - enemy.anchorY;
            const distFromAnchor = Math.hypot(dxFromAnchor, dyFromAnchor);

            // If outside wanderRadius, steer back toward the anchor center
            let goalX = desiredX;
            let goalY = desiredY;
            if (distFromAnchor > enemy.wanderRadius) {
                goalX = enemy.anchorX;
                goalY = enemy.anchorY;
            } else {
                enemy.retargetTimerMs -= frameMs;
                const distToTarget = Math.hypot(goalX - enemy.x, goalY - enemy.y);
                if (enemy.retargetTimerMs <= 0 || distToTarget < 10) {
                    const nextPoint = this.pickRandomPointInRadius(
                        enemy.anchorX,
                        enemy.anchorY,
                        enemy.wanderRadius
                    );
                    enemy.targetX = nextPoint.x;
                    enemy.targetY = nextPoint.y;
                    goalX = enemy.targetX;
                    goalY = enemy.targetY;
                    enemy.retargetTimerMs =
                        enemy.retargetMinMs +
                        Math.random() * (enemy.retargetMaxMs - enemy.retargetMinMs);
                }
            }

            const toGoalX = goalX - enemy.x;
            const toGoalY = goalY - enemy.y;
            const dist = Math.hypot(toGoalX, toGoalY) || 1;

            const desiredVx = (toGoalX / dist) * maxSpeed;
            const desiredVy = (toGoalY / dist) * maxSpeed;

            enemy.vx += (desiredVx - enemy.vx) * Math.min(1, accel * dt);
            enemy.vy += (desiredVy - enemy.vy) * Math.min(1, accel * dt);

            enemy.x += enemy.vx * dt;
            enemy.y += enemy.vy * dt;

            // Keep within playfield horizontal bounds and hard-clamp inside wanderRadius
            enemy.x = Math.max(20, Math.min(gameWidth - 20, enemy.x));
            const finalDx = enemy.x - enemy.anchorX;
            const finalDy = enemy.y - enemy.anchorY;
            const finalDist = Math.hypot(finalDx, finalDy);
            if (finalDist > enemy.wanderRadius) {
                const scale = enemy.wanderRadius / finalDist;
                enemy.x = enemy.anchorX + finalDx * scale;
                enemy.y = enemy.anchorY + finalDy * scale;
            }
        },
        createEnemy(y, forcedType = null) {
            const size = 30;
            const allowedTypes = ["fly", "spider", "lizard"];
            const type = forcedType || allowedTypes[Math.floor(Math.random() * allowedTypes.length)];
            const anchorX = 115 + Math.random() * (gameWidth - 230);
            const anchorY = y;

            const enemy = {
                type: type,
                x: anchorX,
                y: anchorY,
                size: size,
                animAxis: "x",
                flipX: 1,
                flipY: 1,
                despawned: false
            };

            if (type === "fly") {
                this.initRadiusMotion(
                    enemy,
                    anchorX,
                    anchorY,
                    this.flyWanderRadius,
                    this.flySpeed,
                    this.flyAcceleration,
                    this.flyRetargetMinMs,
                    this.flyRetargetMaxMs
                );
            } else if (type === "spider") {
                this.initRadiusMotion(
                    enemy,
                    anchorX,
                    anchorY,
                    this.spiderWanderRadius,
                    this.spiderSpeed,
                    this.spiderAcceleration,
                    999999,
                    999999
                );

                const boxW = Math.min(gameWidth, this.spiderBoxWidth);
                const boxH = this.spiderBoxHeight;
                const clampedBoxX = Math.max(0, Math.min(gameWidth - boxW, anchorX - boxW / 2));

                enemy.box = {
                    x: clampedBoxX,
                    y: anchorY - boxH / 2,
                    width: boxW,
                    height: boxH
                };
                enemy.alerted = false;
                enemy.reactionTimerMs = 0;
                enemy.hasReacted = false;
            } else if (type === "lizard") {
                const fromLeft = Math.random() < 0.5;
                const dir = fromLeft ? 1 : -1;
                // Head peeks slightly inside the left or right edge while body hides behind the border
                const peekX = fromLeft
                    ? this.lizardPeekOffsetPx
                    : gameWidth - this.lizardPeekOffsetPx;

                enemy.dir = dir;
                enemy.x = peekX;
                enemy.y = anchorY;
                enemy.vx = 0;
                enemy.vy = 0;
                enemy.lizardState = "waiting"; // "waiting" | "flashing" | "moving"
                enemy.flashTimerMs = 0;
                enemy.hitboxWidth = this.lizardHitboxWidth;
                enemy.hitboxHeight = this.lizardHitboxHeight;
            }

            this.enemies.push(enemy);
        },
        generateInitialEnemies() {
            this.enemies = [];
            for (let i = 0; i < 8; i++) {
                // Keep the very first enemy a fly so early climbing stays welcoming
                const initialType = i === 0 ? "fly" : null;
                this.createEnemy(canvas.height - 250 - i * this.enemyMinVerticalSpacing, initialType);
            }
        },
        generateEnemies() {
            let highestY = this.enemies.length > 0
                ? Math.min(...this.enemies.map((e) => e.y))
                : this.cameraY;

            while (highestY > this.cameraY - 300) {
                highestY -= this.enemyMinVerticalSpacing + Math.random() * 180;
                this.createEnemy(highestY, null);
            }

            this.enemies = this.enemies.filter((e) => {
                return e.y < this.cameraY + canvas.height + 200;
            });
        },
        updateEnemies(dt, playerState) {
            for (let e of this.enemies) {
                if (e.type === "fly") {
                    this.stepRadiusMotion(
                        dt,
                        e,
                        e.targetX,
                        e.targetY,
                        e.moveSpeed,
                        e.accel
                    );
                } else if (e.type === "spider") {
                    const frameMs = dt * (1000 / 60);
                    const detected = this.isPlayerInEnemyBox(e, playerState);
                    e.alerted = detected;

                    if (detected && playerState) {
                        e.reactionTimerMs = Math.min(
                            this.spiderReactionDelayMs,
                            (e.reactionTimerMs || 0) + frameMs
                        );

                        if (e.reactionTimerMs >= this.spiderReactionDelayMs) {
                            e.hasReacted = true;
                            const playerCenterX = playerState.x + playerState.width / 2;
                            const playerCenterY = playerState.y + playerState.height / 2;

                            const clampedX = Math.max(
                                e.box.x + e.size / 2,
                                Math.min(e.box.x + e.box.width - e.size / 2, playerCenterX)
                            );
                            const clampedY = Math.max(
                                e.box.y + e.size / 2,
                                Math.min(e.box.y + e.box.height - e.size / 2, playerCenterY)
                            );

                            e.targetX = clampedX;
                            e.targetY = clampedY;
                            this.stepRadiusMotion(
                                dt,
                                e,
                                e.targetX,
                                e.targetY,
                                this.spiderSpeed,
                                this.spiderAcceleration
                            );
                        } else {
                            // Hold steady / settle while winding up the 2-second reaction
                            e.vx *= Math.pow(0.8, dt);
                            e.vy *= Math.pow(0.8, dt);
                            e.x += e.vx * dt;
                            e.y += e.vy * dt;
                        }
                    } else {
                        // Player left detection box: reset reaction delay and return to stationed center
                        e.reactionTimerMs = 0;
                        e.hasReacted = false;
                        e.targetX = e.anchorX;
                        e.targetY = e.anchorY;
                        this.stepRadiusMotion(
                            dt,
                            e,
                            e.anchorX,
                            e.anchorY,
                            this.spiderReturnSpeed,
                            this.spiderAcceleration
                        );
                    }
                } else if (e.type === "lizard") {
                    const frameMs = dt * (1000 / 60);

                    if (e.lizardState === "waiting" && playerState) {
                        const playerCenterX = playerState.x + playerState.width / 2;
                        const playerCenterY = playerState.y + playerState.height / 2;

                        const verticalAligned =
                            Math.abs(playerCenterY - e.y) <= this.lizardSightToleranceY ||
                            (playerState.y <= e.y + this.lizardSightToleranceY &&
                             playerState.y + playerState.height >= e.y - this.lizardSightToleranceY);

                        const forwardDist = (playerCenterX - e.x) * e.dir;
                        const inHorizontalSight = forwardDist > 0 && forwardDist <= this.lizardSightRangeX;

                        if (verticalAligned && inHorizontalSight) {
                            e.lizardState = "flashing";
                            e.flashTimerMs = this.lizardFlashDurationMs;
                        }
                    } else if (e.lizardState === "flashing") {
                        e.flashTimerMs -= frameMs;
                        if (e.flashTimerMs <= 0) {
                            e.lizardState = "moving";
                            e.vx = e.dir * this.lizardMoveSpeed;
                        }
                    } else if (e.lizardState === "moving") {
                        e.x += e.vx * dt;
                        if (e.x < -120 || e.x > gameWidth + 120) {
                            e.despawned = true;
                        }
                    }
                }
            }
            this.enemies = this.enemies.filter((e) => !e.despawned);
            this.generateEnemies();
        },

        createRock() {
            this.rocks.push({
                x: Math.random() * gameWidth,
                y: this.cameraY - 50,
                size: Math.random() * 8 + 8,
                speed: Math.random() * 1.5 + 1
            });
        },
        updateRocks(dt) {
            this.rockTimer += dt;
            if (this.rockTimer >= 180) {
                this.rockTimer = 0;
                this.createRock();
            }
            for (let r of this.rocks) {
                r.y += r.speed * dt;
            }
            this.rocks = this.rocks.filter((r) => {
                return r.y - r.size < this.waterY;
            });
        },

        updateCameraAndScoring(dt, playerState, onWaterContact, onFinishFallRespawn) {
            // 1. Base water rise + 75% only when the spider surpasses its previous highest climb
            let newClimbPx = 0;
            if (!playerState.isFallingOutOfBounds) {
                const currentClimbPx = Math.max(0, this.startY - playerState.y);
                if (currentClimbPx > this.maxClimbPx) {
                    newClimbPx = currentClimbPx - this.maxClimbPx;
                    this.maxClimbPx = currentClimbPx;
                }
            }
            this.lastPlayerY = playerState.y;

            this.waterY -= (this.waterRiseSpeed * dt) + (newClimbPx * this.waterClimbRiseFactor);
            const dtSeconds = dt / 60;
            this.waterScrollX += this.waterScrollSpeedX * dtSeconds;

            // 2. Camera follows the spider's vertical movement (both upward and downward)
            if (!playerState.isFallingOutOfBounds) {
                const desiredCameraY = playerState.y - canvas.height / 2;
                const cameraFollowRate = 0.14;

                this.cameraY += (desiredCameraY - this.cameraY) * Math.min(1, cameraFollowRate * dt);
            }

            // 3. Generate ahead and only despawn platforms once touched by the rising water
            this.generatePlatforms();
            this.platforms = this.platforms.filter((p) => {
                return p.y < this.waterY;
            });

            // 4. Update height score & best record
            this.score = Math.max(this.score, Math.floor((this.startY - playerState.y) / 10));
            if (this.score > this.bestScore) {
                this.bestScore = this.score;
                this.newRecord = true;
                try {
                    localStorage.setItem("platformBestScore", String(this.bestScore));
                } catch (e) {}
            }

            // 5. Water contact triggers the out-of-bounds coyote hop & respawn flow
            const screenBottom = this.cameraY + canvas.height;
            if (!playerState.isFallingOutOfBounds && playerState.y + playerState.height >= this.waterY) {
                onWaterContact(playerState);
            } else if (
                playerState.isFallingOutOfBounds &&
                playerState.y > screenBottom + playerState.fallRespawnOffsetY
            ) {
                onFinishFallRespawn(playerState);
            }
        },

        draw(context) {
            const wrapMod = (val, mod) => ((val % mod) + mod) % mod;

            // 1-a. Outer backdrop & clipped looping background (drawn FIRST, behind entities)
            context.fillStyle = "#0f172a";
            context.fillRect(0, 0, canvas.width, canvas.height);

            context.save();
            context.beginPath();
            context.rect(gameLeft, 0, gameWidth, canvas.height);
            context.clip();

            const bg = this.backgroundSprite;
            if (bg.loaded && bg.image.naturalWidth > 0 && bg.image.naturalHeight > 0) {
                const tileW = Math.max(16, Math.round(bg.image.naturalWidth * bg.scale));
                const tileH = Math.max(16, Math.round(bg.image.naturalHeight * bg.scale));
                const offsetY = Math.round(wrapMod(-this.cameraY * 0.5, tileH));
                const startY = -tileH + offsetY;

                context.imageSmoothingEnabled = false;
                for (let y = startY; y < canvas.height + tileH; y += tileH) {
                    for (let x = Math.round(gameLeft); x < gameLeft + gameWidth + tileW; x += tileW) {
                        context.drawImage(bg.image, x, y, tileW + 1, tileH + 1);
                    }
                }
            } else {
                context.fillStyle = "#c6c6c6ff";
                context.fillRect(gameLeft, 0, gameWidth, canvas.height);
            }
            context.restore();

            // 1-b. Background enemy pass (behind platforms): spider webs & lizards
            const webAsset = this.spiderWebSprite;
            const webReady =
                webAsset &&
                webAsset.loaded &&
                webAsset.image.naturalWidth > 0 &&
                webAsset.image.naturalHeight > 0;

            for (let e of this.enemies) {
                if (e.type === "spider" && webReady) {
                    const webCenterX = gameLeft + e.anchorX;
                    const webCenterY = e.anchorY - this.cameraY;
                    const aspectRatio = webAsset.image.naturalWidth / webAsset.image.naturalHeight;
                    const drawSize = (e.wanderRadius || e.size * 2) * 1.15 * (webAsset.scale || this.spiderWebVisualScale);
                    const drawWidth = aspectRatio >= 1 ? drawSize : drawSize * aspectRatio;
                    const drawHeight = aspectRatio >= 1 ? drawSize / aspectRatio : drawSize;

                    context.save();
                    context.imageSmoothingEnabled = false;
                    context.drawImage(
                        webAsset.image,
                        webCenterX - drawWidth / 2,
                        webCenterY - drawHeight / 2,
                        drawWidth,
                        drawHeight
                    );
                    context.restore();
                } else if (e.type === "lizard") {
                    this.drawSingleEnemy(context, e);
                }
            }

            // 2. Platforms (normal & 2 crumbly variants with escalating shake)
            for (let p of this.platforms) {
                if (p.broken) continue;

                let shakeX = 0;
                let shakeY = 0;

                if (p.isCrumbly && p.crumbleTriggered) {
                    const progress = Math.min(1, p.crumbleElapsedMs / this.crumblyBreakDurationMs);
                    const intensity = progress * progress;
                    const amplitude = intensity * this.crumblyMaxShakePx;
                    shakeX = (Math.random() * 2 - 1) * amplitude;
                    shakeY = (Math.random() * 2 - 1) * (amplitude * 0.35);
                }

                const drawX = gameLeft + p.x + shakeX;
                const drawY = p.y - this.cameraY + shakeY;

                if (p.isFinishLine) {
                    context.save();
                    context.fillStyle = "#facc15";
                    context.fillRect(drawX, drawY, p.width, p.height);
                    context.strokeStyle = "#0f172a";
                    context.lineWidth = 2;
                    context.strokeRect(drawX, drawY, p.width, p.height);

                    context.fillStyle = "#0f172a";
                    context.font = "bold 12px 'Courier New', Courier, monospace";
                    context.textAlign = "center";
                    context.fillText("FINISH", drawX + p.width / 2, drawY - 6);
                    context.restore();
                    continue;
                }

                const platAsset = p.isCrumbly
                    ? (this.crumblySprites[p.crumblyVariantIndex] || this.crumblySprites[0])
                    : this.platformSprite;

                if (platAsset && platAsset.loaded && platAsset.image.naturalWidth > 0 && platAsset.image.naturalHeight > 0) {
                    context.save();
                    context.imageSmoothingEnabled = false;
                    context.drawImage(platAsset.image, drawX, drawY, p.width, p.height);
                    context.restore();
                } else {
                    context.fillStyle = p.isCrumbly ? "#854d0e" : "#393939ff";
                    context.fillRect(drawX, drawY, p.width, p.height);
                }
            }

            // 3. Food (3 randomized variants)
            let t = performance.now() / 200;
            for (let f of this.foods) {
                let bob = Math.sin(t + f.x) * 3;
                const centerX = gameLeft + f.x;
                const centerY = f.y - this.cameraY + bob;
                const foodAsset = this.foodSprites[f.variantIndex] || this.foodSprites[0];

                if (foodAsset && foodAsset.loaded && foodAsset.image.naturalWidth > 0 && foodAsset.image.naturalHeight > 0) {
                    const aspectRatio = foodAsset.image.naturalWidth / foodAsset.image.naturalHeight;
                    const drawHeight = this.foodRadius * 2 * (foodAsset.scale || this.foodVisualScale);
                    const drawWidth = drawHeight * aspectRatio;

                    context.save();
                    context.imageSmoothingEnabled = false;
                    context.drawImage(
                        foodAsset.image,
                        centerX - drawWidth / 2,
                        centerY - drawHeight / 2,
                        drawWidth,
                        drawHeight
                    );
                    context.restore();
                } else {
                    context.beginPath();
                    context.arc(centerX, centerY, this.foodRadius, 0, Math.PI * 2);
                    context.fillStyle = "gold";
                    context.fill();
                    context.lineWidth = 2;
                    context.strokeStyle = "#b8860b";
                    context.stroke();
                }
            }

            // 4. Foreground enemy pass (in front of platforms): flies & spiders
            for (let e of this.enemies) {
                if (e.type === "fly" || e.type === "spider") {
                    this.drawSingleEnemy(context, e);
                }
            }

            // 5. Rain droplets
            const dropSprite = this.raindropSprite;
            for (let r of this.rocks) {
                const dropCenterX = gameLeft + r.x;
                const dropCenterY = r.y - this.cameraY;
                const baseDiameter = r.size * 2;

                if (dropSprite.loaded && dropSprite.image.naturalWidth > 0 && dropSprite.image.naturalHeight > 0) {
                    const aspectRatio = dropSprite.image.naturalWidth / dropSprite.image.naturalHeight;
                    const drawHeight = baseDiameter * dropSprite.scale;
                    const drawWidth = drawHeight * aspectRatio;

                    context.save();
                    context.imageSmoothingEnabled = false;
                    context.drawImage(
                        dropSprite.image,
                        dropCenterX - drawWidth / 2,
                        dropCenterY - drawHeight / 2,
                        drawWidth,
                        drawHeight
                    );
                    context.restore();
                } else {
                    context.beginPath();
                    context.arc(dropCenterX, dropCenterY, r.size, 0, Math.PI * 2);
                    context.fillStyle = "#555555";
                    context.fill();
                }
            }

            // 6. Rising water: seamless loop with rounded coordinates & 1px tile overlap
            const visualWaterScreenY = (this.waterY + this.waterVisualOffsetY) - this.cameraY;
            if (visualWaterScreenY < canvas.height + 40) {
                const nowSec = performance.now() / 1000;
                const wAsset = this.waterSprite;
                const bobY = Math.sin(nowSec * this.waterBobSpeed) * this.waterBobAmpPx;

                context.save();
                context.beginPath();
                context.rect(gameLeft, 0, gameWidth, canvas.height);
                context.clip();

                if (wAsset.loaded && wAsset.image.naturalWidth > 0 && wAsset.image.naturalHeight > 0) {
                    const tileW = Math.max(32, Math.round(wAsset.image.naturalWidth * wAsset.scale));
                    const tileH = Math.max(32, Math.round(wAsset.image.naturalHeight * wAsset.scale));

                    const scrollOffsetX = Math.round(wrapMod(this.waterScrollX, tileW));
                    const startX = Math.round(gameLeft - tileW + scrollOffsetX);
                    const topY = Math.round(visualWaterScreenY + bobY);

                    const fillStartY = Math.max(0, Math.floor(topY + tileH * 0.5));
                    if (fillStartY < canvas.height) {
                        context.fillStyle = "#595959";
                        context.fillRect(gameLeft, fillStartY, gameWidth, canvas.height - fillStartY);
                    }

                    context.imageSmoothingEnabled = false;
                    for (let y = topY; y < canvas.height + tileH; y += tileH) {
                        for (let x = startX; x < gameLeft + gameWidth + tileW; x += tileW) {
                            context.drawImage(wAsset.image, x, y, tileW + 1, tileH + 1);
                        }
                    }
                } else {
                    const drawTop = Math.max(0, visualWaterScreenY + bobY);
                    const drawHeight = canvas.height - drawTop;
                    context.fillStyle = "rgba(14, 165, 233, 0.55)";
                    context.fillRect(gameLeft, drawTop, gameWidth, drawHeight);
                }

                context.restore();
            }
            
            // 7. Left & right playfield edge borders (keeps top HUD unobstructed)
            context.save();
            context.fillStyle = "#ffffff";
            if (gameLeft > 0) {
                context.fillRect(0, 0, gameLeft, canvas.height);
            }
            const rightBorderX = gameLeft + gameWidth;
            const rightBorderW = canvas.width - rightBorderX;
            if (rightBorderW > 0) {
                context.fillRect(rightBorderX, 0, rightBorderW, canvas.height);
            }
            context.restore();
        }
    };

    // ==========================================
    // GAME FLOW, HUD & UI
    // ==========================================
    function handlePlayerFallOutOfBounds(playerState) {
        PlayerSystem.clearAllInputs();
        const remainingLives = deductLifeOnly();

        if (remainingLives <= 0) {
            PlayerSystem.state.hitstopTimerMs = 0;
            triggerGameOver();
            return;
        }

        // Play hitstop on life loss, then launch the coyote hop before falling off-screen
        AudioSystem.playHurt();
        PlayerSystem.triggerHitstop();
        PlayerSystem.startFallOutOfBoundsSequence();
    }
    function finishFallOutOfBoundsRespawn(playerState) {
        const targetPlatform = TerrainSystem.findBottomScreenPlatform();
        const fallbackX = gameWidth / 2 - PlayerSystem.state.width / 2;
        const safeWaterCeilingY =
            TerrainSystem.waterY - TerrainSystem.respawnEscapeMarginY - PlayerSystem.state.height;
        const fallbackY = Math.min(
            TerrainSystem.cameraY + canvas.height - 140,
            safeWaterCeilingY
        );

        PlayerSystem.respawnOnPlatform(targetPlatform, fallbackX, fallbackY);

        if (PlayerSystem.state.y > safeWaterCeilingY) {
            PlayerSystem.state.y = safeWaterCeilingY;
            PlayerSystem.state.grounded = false;
        }

        // Guarantee breathing room below the respawned spider and align camera so water doesn't flood the screen
        const minWaterY = PlayerSystem.state.y + PlayerSystem.state.height + TerrainSystem.respawnEscapeMarginY;
        if (TerrainSystem.waterY < minWaterY) {
            TerrainSystem.waterY = minWaterY;
        }

        TerrainSystem.cameraY = PlayerSystem.state.y - canvas.height * 0.55;
        TerrainSystem.lastPlayerY = PlayerSystem.state.y;
        playerState.y = PlayerSystem.state.y;
    }
    function startFreshRun() {
        PlayerSystem.clearAllInputs();
        gameState = "playing";

        const spawnX = gameWidth / 2 - PlayerSystem.state.width / 2;
        const spawnY = canvas.height - 150;
        PlayerSystem.resetState(spawnX, spawnY);
        TerrainSystem.resetRunState(PlayerSystem.state, true);

        AudioSystem.stopAndRewindBgm();
        if (AudioSystem.unlocked) {
            AudioSystem.playBgm();
        }
    }
    function resetGame() {
        PlayerSystem.clearAllInputs();
        TerrainSystem.lives -= 1;

        if (TerrainSystem.lives <= 0) {
            TerrainSystem.lives = 0;
            gameState = "gameover";

            if (TerrainSystem.newRecord) {
                fetch('../config/save_score.php', {
                    method: 'POST',
                    body: new URLSearchParams({
                        bestFoods: TerrainSystem.bestFoods,
                        bestScore: TerrainSystem.bestScore
                    })
                })
                .then(res => {
                    if (res.ok) TerrainSystem.newRecord = false;
                });
            }
            return;
        }

        const spawnX = gameWidth / 2 - PlayerSystem.state.width / 2;
        const spawnY = canvas.height - 150;
        PlayerSystem.resetState(spawnX, spawnY);
        TerrainSystem.resetRunState(PlayerSystem.state, false);
    }
    function deductLifeOnly() {
        TerrainSystem.lives = Math.max(0, TerrainSystem.lives - 1);
        ChallengeSystem.recordHit();
        return TerrainSystem.lives;
    }

    function pauseGame() {
        if (gameState !== "playing") return;
        PlayerSystem.clearAllInputs();
        gameState = "paused";
        AudioSystem.pauseBgm();
    }
    function resumeGame() {
        if (gameState !== "paused") return;
        PlayerSystem.clearAllInputs();
        lastTime = performance.now();
        gameState = "playing";
        AudioSystem.playBgm();
    }
    function triggerGameOver() {
        PlayerSystem.clearAllInputs();
        TerrainSystem.lives = 0;
        gameState = "gameover";
        AudioSystem.playGameOver();

        if (TerrainSystem.newRecord) {
            fetch('../config/save_score.php', {
                method: 'POST',
                body: new URLSearchParams({
                    bestFoods: TerrainSystem.bestFoods,
                    bestScore: TerrainSystem.bestScore
                })
            })
            .then(res => {
                if (res.ok) TerrainSystem.newRecord = false;
            });
        }
    }

    function completeStage() {
        if (gameState !== "playing") return;
        PlayerSystem.clearAllInputs();
        gameState = "victory";
        AudioSystem.stopAndRewindBgm();

        if (TerrainSystem.newRecord) {
            fetch('../config/save_score.php', {
                method: 'POST',
                body: new URLSearchParams({
                    bestFoods: TerrainSystem.bestFoods,
                    bestScore: TerrainSystem.bestScore
                })
            })
            .then(res => {
                if (res.ok) TerrainSystem.newRecord = false;
            });
        }
    }
    function drawVictoryOverlay() {
        if (gameState !== "victory") return;

        ctx.save();
        ctx.fillStyle = "rgba(15, 23, 42, 0.82)";
        ctx.fillRect(gameLeft, 0, gameWidth, canvas.height);

        const centerX = gameLeft + gameWidth / 2;
        const panelTop = canvas.height / 2 - 140;

        ctx.textAlign = "center";
        ctx.fillStyle = "#facc15";
        ctx.font = "bold 36px 'Courier New', Courier, monospace";
        ctx.fillText("STAGE COMPLETE!", centerX, panelTop);

        ctx.font = "bold 18px 'Courier New', Courier, monospace";
        ctx.fillStyle = "#e2e8f0";
        ctx.fillText(
            `Foods: ${TerrainSystem.foodCount}/${StageConfig.targetFoods}  |  Time: ${ChallengeSystem.elapsedSec.toFixed(1)}s`,
            centerX,
            panelTop + 42
        );

        const challengePassed = ChallengeSystem.isCompleted();
        ctx.fillStyle = challengePassed ? "#4ade80" : "#f87171";
        ctx.fillText(
            `${ChallengeSystem.getTitle()}: ${challengePassed ? "COMPLETED" : "NOT MET"}`,
            centerX,
            panelTop + 76
        );

        victoryButtons.retry.x = centerX - victoryButtons.retry.width / 2;
        victoryButtons.retry.y = panelTop + 124;
        ctx.fillStyle = "#aeb7b9";
        ctx.fillRect(victoryButtons.retry.x, victoryButtons.retry.y, victoryButtons.retry.width, victoryButtons.retry.height);
        ctx.strokeStyle = "#ffffff";
        ctx.lineWidth = 2;
        ctx.strokeRect(victoryButtons.retry.x, victoryButtons.retry.y, victoryButtons.retry.width, victoryButtons.retry.height);
        ctx.fillStyle = "#0f172a";
        ctx.font = "bold 18px 'Courier New', Courier, monospace";
        ctx.fillText("Play Again", centerX, victoryButtons.retry.y + victoryButtons.retry.height / 2 + 1);

        victoryButtons.mainMenu.x = centerX - victoryButtons.mainMenu.width / 2;
        victoryButtons.mainMenu.y = panelTop + 186;
        ctx.fillStyle = "#6d7177";
        ctx.fillRect(victoryButtons.mainMenu.x, victoryButtons.mainMenu.y, victoryButtons.mainMenu.width, victoryButtons.mainMenu.height);
        ctx.strokeStyle = "#ffffff";
        ctx.lineWidth = 2;
        ctx.strokeRect(victoryButtons.mainMenu.x, victoryButtons.mainMenu.y, victoryButtons.mainMenu.width, victoryButtons.mainMenu.height);
        ctx.fillStyle = "#ffffff";
        ctx.fillText("Main Menu", centerX, victoryButtons.mainMenu.y + victoryButtons.mainMenu.height / 2 + 1);

        ctx.restore();
    }
    function isPointInRect(px, py, rect) {
        return (
            px >= rect.x &&
            px <= rect.x + rect.width &&
            py >= rect.y &&
            py <= rect.y + rect.height
        );
    }

    // Input & UI Event Listeners
    canvas.addEventListener("contextmenu", function(event) {
        event.preventDefault();
    });
    document.addEventListener("keydown", function(event) {
        AudioSystem.unlockAndSync(gameState);

        if (event.key.toLowerCase() === "m") {
            AudioSystem.toggleMute();
            return;
        }

        if (event.key === "Escape" || event.key.toLowerCase() === "p") {
            if (gameState === "playing") {
                pauseGame();
                return;
            } else if (gameState === "paused") {
                resumeGame();
                return;
            }
        }

        if (gameState !== "playing") return;
        PlayerSystem.handleKeyInput(event, true);
    });
    document.addEventListener("keyup", function(event) {
        PlayerSystem.handleKeyInput(event, false);
    });
    canvas.addEventListener("pointerdown", function(event) {
        AudioSystem.unlockAndSync(gameState);

        const rect = canvas.getBoundingClientRect();
        const clickX = event.clientX - rect.left;
        const clickY = event.clientY - rect.top;

        if (gameState === "paused") {
            if (event.button !== 0) return;
            if (isPointInRect(clickX, clickY, pauseButtons.resume)) {
                resumeGame();
            } else if (isPointInRect(clickX, clickY, pauseButtons.restart)) {
                startFreshRun();
            } else if (isPointInRect(clickX, clickY, pauseButtons.mainMenu)) {
                window.location.href = "main_menu.php";
            }
            return;
        }
        if (gameState === "gameover") {
            if (event.button !== 0) return;
            if (isPointInRect(clickX, clickY, gameOverButtons.retry)) {
                startFreshRun();
            } else if (isPointInRect(clickX, clickY, gameOverButtons.mainMenu)) {
                window.location.href = "main_menu.php";
            }
            return;
        }
        if (gameState === "victory") {
            if (event.button !== 0) return;
            if (isPointInRect(clickX, clickY, victoryButtons.retry)) {
                startFreshRun();
            } else if (isPointInRect(clickX, clickY, victoryButtons.mainMenu)) {
                window.location.href = "main_menu.php";
            }
            return;
        }

        // Right-click converts full hunger bar into +1 life
        if (event.button === 2) {
            TerrainSystem.convertFullHungerToLife(PlayerSystem.state);
            return;
        }

        if (event.button !== 0) return;

        // Check sidebar Pause button before starting super-jump charge
        if (isPointInRect(clickX, clickY, pauseButton)) {
         pauseGame();
         return;
        }

        if (isPointInRect(clickX, clickY, musicToggleButton)) {
            AudioSystem.toggleBgmMute();
            return;
        }

        PlayerSystem.setSuperJumpHoldInput(true);
    });
    window.addEventListener("pointerup", function(event) {
        if (event.button === 0) {
            PlayerSystem.setSuperJumpHoldInput(false);
        }
    });
    window.addEventListener("pointercancel", function() {
        PlayerSystem.setSuperJumpHoldInput(false);
    });
    window.addEventListener("blur", function() {
        PlayerSystem.clearAllInputs();
    });
    window.addEventListener("resize", function() {
        syncViewportLayout();
    });
    function drawHud() {
        ctx.save();
        ctx.fillStyle = "#030303";
        ctx.font = "bold 20px 'Courier New', Courier, monospace";

        // Right sidebar outside the central 600px column
        const rightSidebarX = Math.min(canvas.width - 16, gameLeft + gameWidth + 20);
        ctx.textAlign = "left";
        ctx.fillText("Lives Left: " + TerrainSystem.lives + " / " + PlayerSystem.state.maxLives, rightSidebarX, 36);
        ctx.fillText("Food: " + TerrainSystem.foodCount, rightSidebarX, 64);
        ctx.fillText("Height: " + TerrainSystem.score, rightSidebarX, 92);

        // Endless mode food-to-life meter
        const foodRequired = PlayerSystem.state.foodToLives || 15;
        const hungerFull = TerrainSystem.foodProgress >= foodRequired;
        const atMaxLives = TerrainSystem.lives >= PlayerSystem.state.maxLives;

        let meterLabel = `Hunger: ${TerrainSystem.foodProgress}/${foodRequired}`;
        if (hungerFull && !atMaxLives) {
            meterLabel = "Hunger: FULL [RMB for +1 Life]";
        } else if (hungerFull && atMaxLives) {
            meterLabel = "Hunger: FULL (Max Lives)";
        }

        ctx.font = "bold 16px 'Courier New', Courier, monospace";
        ctx.fillText(meterLabel, rightSidebarX, 122);

        const barWidth = 170;
        const barHeight = 10;
        const barY = 132;
        const ratio = Math.min(1, TerrainSystem.foodProgress / foodRequired);

        ctx.fillStyle = "#334155";
        ctx.fillRect(rightSidebarX, barY, barWidth, barHeight);
        ctx.fillStyle = hungerFull ? "#eab308" : "#22c55e";
        ctx.fillRect(rightSidebarX, barY, barWidth * ratio, barHeight);

        const challengeY = musicToggleButton.y + musicToggleButton.height + 24;
        ctx.textAlign = "left";
        ctx.textBaseline = "alphabetic";
        ctx.fillStyle = "#0f172a";
        ctx.font = "bold 13px 'Courier New', Courier, monospace";
        ctx.fillText(ChallengeSystem.getTitle(), rightSidebarX, challengeY, 210);

        const passedNow = ChallengeSystem.isCompleted();
        ctx.fillStyle = passedNow ? "#15803d" : "#b91c1c";
        ctx.fillText(ChallengeSystem.getStatusText(), rightSidebarX, challengeY + 18, 210);

        // Pause button in the right sidebar while playing
        if (gameState === "playing") {
            pauseButton.x = rightSidebarX;
            pauseButton.y = 158;
            pauseButton.width = 170;

            ctx.fillStyle = "#1e293b";
            ctx.fillRect(pauseButton.x, pauseButton.y, pauseButton.width, pauseButton.height);
            ctx.strokeStyle = "#0f172a";
            ctx.lineWidth = 2;
            ctx.strokeRect(pauseButton.x, pauseButton.y, pauseButton.width, pauseButton.height);

            ctx.fillStyle = "#ffffff";
            ctx.font = "bold 16px 'Courier New', Courier, monospace";
            ctx.textAlign = "center";
            ctx.textBaseline = "middle";
            ctx.fillText(
                "Pause",
                pauseButton.x + pauseButton.width / 2,
                pauseButton.y + pauseButton.height / 2 + 1
            );

            // Music-only toggle button
            musicToggleButton.x = rightSidebarX;
            musicToggleButton.y = pauseButton.y + pauseButton.height + 10;
            musicToggleButton.width = 170;
            musicToggleButton.height = 36;

            const musicOff = AudioSystem.isBgmEffectivelyMuted();
            ctx.fillStyle = musicOff ? "#475569" : "#262c38";
            ctx.fillRect(
                musicToggleButton.x,
                musicToggleButton.y,
                musicToggleButton.width,
                musicToggleButton.height
            );
            ctx.strokeStyle = musicOff ? "#94a3b8" : "#acb2b4";
            ctx.lineWidth = 2;
            ctx.strokeRect(
                musicToggleButton.x,
                musicToggleButton.y,
                musicToggleButton.width,
                musicToggleButton.height
            );

            ctx.fillStyle = musicOff ? "#cbd5e1" : "#ffffff";
            ctx.font = "bold 14px 'Courier New', Courier, monospace";
            ctx.fillText(
                musicOff ? "Music: OFF" : "Music: ON",
                musicToggleButton.x + musicToggleButton.width / 2,
                musicToggleButton.y + musicToggleButton.height / 2 + 1
            );
        }

        ctx.restore();
    }

    function drawPauseOverlay() {
        if (gameState !== "paused") return;

        ctx.save();
        ctx.fillStyle = "rgba(15, 23, 42, 0.78)";
        ctx.fillRect(gameLeft, 0, gameWidth, canvas.height);

        const centerX = gameLeft + gameWidth / 2;
        const panelTop = canvas.height / 2 - 130;

        ctx.textAlign = "center";
        ctx.fillStyle = "#6aa6c2";
        ctx.font = "bold 36px 'Courier New', Courier, monospace";
        ctx.fillText("PAUSED", centerX, panelTop);

        ctx.font = "bold 18px 'Courier New', Courier, monospace";
        ctx.fillStyle = "#e2e8f0";
        ctx.fillText("Height: " + TerrainSystem.score + "  |  Food: " + TerrainSystem.foodCount, centerX, panelTop + 42);

        // Resume button
        pauseButtons.resume.x = centerX - pauseButtons.resume.width / 2;
        pauseButtons.resume.y = panelTop + 74;

        ctx.fillStyle = "#d8d8d8";
        ctx.fillRect(
            pauseButtons.resume.x,
            pauseButtons.resume.y,
            pauseButtons.resume.width,
            pauseButtons.resume.height
        );
        ctx.strokeStyle = "#ffffff";
        ctx.lineWidth = 2;
        ctx.strokeRect(
            pauseButtons.resume.x,
            pauseButtons.resume.y,
            pauseButtons.resume.width,
            pauseButtons.resume.height
        );
        ctx.fillStyle = "#0f172a";
        ctx.font = "bold 18px 'Courier New', Courier, monospace";
        ctx.fillText(
            "Resume",
            centerX,
            pauseButtons.resume.y + pauseButtons.resume.height / 2 + 1
        );

        // Restart button
        pauseButtons.restart.x = centerX - pauseButtons.restart.width / 2;
        pauseButtons.restart.y = panelTop + 136;

        ctx.fillStyle = "#d8d8d8";
        ctx.fillRect(
            pauseButtons.restart.x,
            pauseButtons.restart.y,
            pauseButtons.restart.width,
            pauseButtons.restart.height
        );
        ctx.strokeStyle = "#ffffff";
        ctx.lineWidth = 2;
        ctx.strokeRect(
            pauseButtons.restart.x,
            pauseButtons.restart.y,
            pauseButtons.restart.width,
            pauseButtons.restart.height
        );
        ctx.fillStyle = "#0f172a";
        ctx.fillText(
            "Restart",
            centerX,
            pauseButtons.restart.y + pauseButtons.restart.height / 2 + 1
        );

        // Main Menu button
        pauseButtons.mainMenu.x = centerX - pauseButtons.mainMenu.width / 2;
        pauseButtons.mainMenu.y = panelTop + 198;

        ctx.fillStyle = "#555555";
        ctx.fillRect(
            pauseButtons.mainMenu.x,
            pauseButtons.mainMenu.y,
            pauseButtons.mainMenu.width,
            pauseButtons.mainMenu.height
        );
        ctx.strokeStyle = "#ffffff";
        ctx.lineWidth = 2;
        ctx.strokeRect(
            pauseButtons.mainMenu.x,
            pauseButtons.mainMenu.y,
            pauseButtons.mainMenu.width,
            pauseButtons.mainMenu.height
        );
        ctx.fillStyle = "#ffffff";
        ctx.fillText(
            "Main Menu",
            centerX,
            pauseButtons.mainMenu.y + pauseButtons.mainMenu.height / 2 + 1
        );

        ctx.restore();
    }

    function drawGameOverOverlay() {
        if (gameState !== "gameover") return;

        ctx.fillStyle = "rgba(15, 23, 42, 0.78)";
        ctx.fillRect(gameLeft, 0, gameWidth, canvas.height);

        const centerX = gameLeft + gameWidth / 2;
        const panelTop = canvas.height / 2 - 140;

        ctx.textAlign = "center";
        ctx.fillStyle = "#ff0000";
        ctx.font = "bold 36px 'Courier New', Courier, monospace";
        ctx.fillText("GAME OVER", centerX, panelTop);

        // Score + Best Score (two-tone)
        ctx.font = "bold 20px 'Courier New', Courier, monospace";
        const scoreCurrent = "Score: " + TerrainSystem.foodCount + " ";
        const scoreBest = "(Best: " + TerrainSystem.bestFoods + ")";
        const scoreTotalW = ctx.measureText(scoreCurrent + scoreBest).width;
        const scoreStartX = centerX - scoreTotalW / 2;
        ctx.textAlign = "left";
        ctx.fillStyle = "#e2e8f0";
        ctx.fillText(scoreCurrent, scoreStartX, panelTop + 42);
        ctx.fillStyle = "#fbbf24";
        ctx.fillText(scoreBest, scoreStartX + ctx.measureText(scoreCurrent).width, panelTop + 42);

        // Height Climbed + Best Height (two-tone)
        const heightCurrent = "Height Climbed: " + TerrainSystem.score + " ";
        const heightBest = "(Best: " + TerrainSystem.bestScore + ")";
        const heightTotalW = ctx.measureText(heightCurrent + heightBest).width;
        const heightStartX = centerX - heightTotalW / 2;
        ctx.fillStyle = "#e2e8f0";
        ctx.fillText(heightCurrent, heightStartX, panelTop + 72);
        ctx.fillStyle = "#38bdf8";
        ctx.fillText(heightBest, heightStartX + ctx.measureText(heightCurrent).width, panelTop + 74);

        // Food Collected
        ctx.textAlign = "center";
        ctx.fillStyle = "#c7a36d";
        ctx.fillText("Food Collected: " + TerrainSystem.foodCount, centerX, panelTop + 104);
        gameOverButtons.retry.x = centerX - gameOverButtons.retry.width / 2;
        gameOverButtons.retry.y = panelTop + 136;

        ctx.fillStyle = "#aeb7b9";
        ctx.fillRect(
            gameOverButtons.retry.x,
            gameOverButtons.retry.y,
            gameOverButtons.retry.width,
            gameOverButtons.retry.height
        );
        ctx.strokeStyle = "#7d8285";
        ctx.lineWidth = 2;
        ctx.strokeRect(
            gameOverButtons.retry.x,
            gameOverButtons.retry.y,
            gameOverButtons.retry.width,
            gameOverButtons.retry.height
        );
        ctx.fillStyle = "#0f172a";
        ctx.font = "bold 18px 'Courier New', Courier, monospace";
        ctx.fillText(
            "Retry",
            centerX,
            gameOverButtons.retry.y + gameOverButtons.retry.height / 2 + 1
        );

        gameOverButtons.mainMenu.x = centerX - gameOverButtons.mainMenu.width / 2;
        gameOverButtons.mainMenu.y = panelTop + 198;

        ctx.fillStyle = "#6d7177";
        ctx.fillRect(
            gameOverButtons.mainMenu.x,
            gameOverButtons.mainMenu.y,
            gameOverButtons.mainMenu.width,
            gameOverButtons.mainMenu.height
        );
        ctx.strokeStyle = "#857878";
        ctx.lineWidth = 2;
        ctx.strokeRect(
            gameOverButtons.mainMenu.x,
            gameOverButtons.mainMenu.y,
            gameOverButtons.mainMenu.width,
            gameOverButtons.mainMenu.height
        );
        ctx.fillStyle = "#ffffff";
        ctx.fillText(
            "Main Menu",
            centerX,
            gameOverButtons.mainMenu.y + gameOverButtons.mainMenu.height / 2 + 1
        );
    }

    function draw() {
        const hideSprite = gameState === "gameover" || gameState === "paused" || gameState === "victory";
        TerrainSystem.draw(ctx);
        PlayerSystem.drawSprite(ctx, TerrainSystem.cameraY, hideSprite);
        drawHud();
        PlayerSystem.drawStatusIndicators(ctx, TerrainSystem.cameraY);
        drawPauseOverlay();
        drawGameOverOverlay();
        drawVictoryOverlay();
        // PlayerSystem.drawGizmos(ctx);
    }
    // Main Loop
    let lastTime = performance.now();
    function gameLoop(now) {
        let dt = Math.min((now - lastTime) / (1000 / 60), 3);
        lastTime = now;

        if (gameState === "playing") {
            const frameMs = dt * (1000 / 60);

            if (PlayerSystem.state.hitstopTimerMs > 0) {
                PlayerSystem.state.hitstopTimerMs = Math.max(0, PlayerSystem.state.hitstopTimerMs - frameMs);
            } else {
                PlayerSystem.tickDamageTimers(dt);
                TerrainSystem.updatePlatforms(dt, PlayerSystem.state);
                PlayerSystem.update(dt, TerrainSystem.platforms);
                TerrainSystem.updateCameraAndScoring(
                    dt,
                    PlayerSystem.state,
                    handlePlayerFallOutOfBounds,
                    finishFallOutOfBoundsRespawn
                );
                TerrainSystem.updateEnemies(dt, PlayerSystem.state);
                TerrainSystem.updateRocks(dt);
                TerrainSystem.updateFoods(PlayerSystem.state);
                PlayerSystem.checkEnemyCollision(TerrainSystem.enemies, deductLifeOnly, triggerGameOver);
                PlayerSystem.checkRockCollision(TerrainSystem.rocks, deductLifeOnly, triggerGameOver);
                ChallengeSystem.update(dt, gameState === "playing")
            }
        }

        draw();
        PlayerSystem.consumeFrameEdgeInputs();
        requestAnimationFrame(gameLoop);
    }

    TerrainSystem.initSprites();
    startFreshRun();
    requestAnimationFrame(gameLoop);
//#endregion

</script>

</body>
</html>
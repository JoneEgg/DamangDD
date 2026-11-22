<?php 
    include('db.php');
    session_start();
    if(!isset($_SESSION['username'])){
        header("Location: login.php");
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
    <title>Damang, Don't Drown!</title>
    <link rel="stylesheet" href="styles.css">
<style>
</style>
</head>
<body id="main_body">
<canvas id="gameCanvas"></canvas>
<img id="spiderGifOverlay" src="images/dante.gif" alt="Dante" style="position: fixed; display: none; pointer-events: none; image-rendering: pixelated; z-index: 10;">
<img id="spiderChargeGifOverlay" src="images/dante_charge.gif" alt="Dante Charging" style="position: fixed; display: none; pointer-events: none; image-rendering: pixelated; z-index: 10;">
<img id="spiderWalkGifOverlay" src="images/dante_walk.gif" alt="Dante Walking" style="position: fixed; display: none; pointer-events: none; image-rendering: pixelated; z-index: 10;">

<script>
//#region Canvas and Session State
    const canvas = document.getElementById("gameCanvas");
    const ctx = canvas.getContext("2d");

    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
    const gameWidth = 600;
    let gameLeft = (canvas.width - gameWidth) / 2;

    let gameState = "playing"; // "playing" | "paused" | "gameover"
    const pauseButton = { x: 0, y: 0, width: 140, height: 38 };
    const gameOverButtons = {
        retry:    { x: 0, y: 0, width: 180, height: 48 },
        mainMenu: { x: 0, y: 0, width: 180, height: 48 }
    };
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
        bgmSrc: "images/game_bgm.mp3", // <-- replace with your music file path
        gameOverSrc: "",                  // <-- optional: e.g. "audio/gameover.mp3"
        bgm: null,
        gameOverSfx: null,
        volume: 0.45,
        muted: false,
        unlocked: false,

        init() {
            this.bgm = new Audio(this.bgmSrc);
            this.bgm.loop = true;
            this.bgm.volume = this.muted ? 0 : this.volume;
            this.bgm.preload = "auto";

            if (this.gameOverSrc) {
                this.gameOverSfx = new Audio(this.gameOverSrc);
                this.gameOverSfx.loop = false;
                this.gameOverSfx.volume = this.muted ? 0 : Math.min(1, this.volume * 1.1);
                this.gameOverSfx.preload = "auto";
            }
        },

        unlockAndSync(currentGameState) {
            this.unlocked = true;
            if (currentGameState === "playing") {
                this.playBgm();
            }
        },

        playBgm() {
            if (!this.bgm || this.muted) return;
            const playPromise = this.bgm.play();
            if (playPromise && typeof playPromise.catch === "function") {
                playPromise.catch(() => {
                    // Autoplay blocked until the user interacts with the page
                });
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
            if (!this.gameOverSfx || this.muted) return;
            this.gameOverSfx.currentTime = 0;
            const playPromise = this.gameOverSfx.play();
            if (playPromise && typeof playPromise.catch === "function") {
                playPromise.catch(() => {});
            }
        },

        setMuted(muteState) {
            this.muted = Boolean(muteState);
            if (this.bgm) {
                this.bgm.volume = this.muted ? 0 : this.volume;
            }
            if (this.gameOverSfx) {
                this.gameOverSfx.volume = this.muted ? 0 : Math.min(1, this.volume * 1.1);
            }
            if (this.muted) {
                this.pauseBgm();
            } else if (this.unlocked && gameState === "playing") {
                this.playBgm();
            }
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
                "images/dante.gif",
                "spiderGifOverlay",
                "Spider sprite failed to load; using fallback box."
            ),
            walk: createSpriteAsset(
                "images/dante_walk.gif",
                "spiderWalkGifOverlay",
                "Spider walk sprite failed to load; using idle spider sprite."
            ),
            charge: createSpriteAsset(
                "images/dante_charge.gif",
                "spiderChargeGifOverlay",
                "Spider charge sprite failed to load; using idle spider sprite."
            ),
            wasGroundedCharging: false,
            wasWalking: false
        },

        getAllAssets() {
            return [this.assets.idle, this.assets.walk, this.assets.charge];
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
                        // Drop-down disabled while charging Super Jump
                        if (!isChargingSuperJump && this.keys.dropDownPressed) {
                            this.keys.dropDownPressed = false;
                            pState.ignorePlatform = p;
                            pState.dropThroughTimer = pState.dropThroughFrames;
                            pState.canExtendJump = false;
                            break;
                        }

                        pState.y = p.y - pState.height;
                        pState.velocityY = 0;
                        pState.grounded = true;
                        if (isChargingSuperJump) {
                            pState.velocityX = 0;
                        }
                        pState.isSuperJumping = false;
                        pState.ignorePlatform = null;
                        pState.dropThroughTimer = 0;

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
                let half = e.size / 2;
                if (
                    p.x < e.x + half &&
                    p.x + p.width > e.x - half &&
                    p.y < e.y + half &&
                    p.y + p.height > e.y - half
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
            const hasEnoughHungerForSuperJump = TerrainSystem.foodProgress >= p.superJumpHungerCost;
            const isGroundedCharging =
                this.keys.superJumpHeld &&
                !p.isSuperJumping &&
                p.grounded &&
                hasEnoughHungerForSuperJump;
            if (isGroundedCharging && isSpriteReady(this.assets.charge)) {
                return this.assets.charge;
            }

            const isMoving = !isGroundedCharging && (this.keys.left || this.keys.right || Math.abs(p.velocityX) > 0);
            if (isMoving && isSpriteReady(this.assets.walk)) {
                return this.assets.walk;
            }

            if (isSpriteReady(this.assets.idle)) {
                return this.assets.idle;
            }
            return null;
        },

        drawSprite(context, cameraY, isGameOver) {
            const p = this.state;
            const hasEnoughHungerForSuperJump = TerrainSystem.foodProgress >= p.superJumpHungerCost;
            const isGroundedCharging =
                !isGameOver &&
                this.keys.superJumpHeld &&
                !p.isSuperJumping &&
                p.grounded &&
                hasEnoughHungerForSuperJump;
            const isWalking =
                !isGameOver &&
                !isGroundedCharging &&
                (this.keys.left || this.keys.right || Math.abs(p.velocityX) > 0);

            if (isGroundedCharging && !this.assets.wasGroundedCharging) {
                restartGifOverlay(this.assets.charge);
            } else if (!isGroundedCharging && this.assets.wasGroundedCharging) {
                hideSpriteOverlay(this.assets.charge);
            }
            this.assets.wasGroundedCharging = isGroundedCharging;

            if (isWalking && !this.assets.wasWalking) {
                restartGifOverlay(this.assets.walk);
            } else if (!isWalking && this.assets.wasWalking) {
                hideSpriteOverlay(this.assets.walk);
            }
            this.assets.wasWalking = isWalking;

            const activeAsset = !isGameOver ? this.selectActiveAsset() : null;
            const hitboxCenterX = gameLeft + p.x + p.width / 2;
            const hitboxBottomY = p.y - cameraY + p.height;

            for (const asset of this.getAllAssets()) {
                if (asset !== activeAsset) {
                    hideSpriteOverlay(asset);
                }
            }

            if (isGameOver) {
                this.assets.wasGroundedCharging = false;
                this.assets.wasWalking = false;
                return;
            }

            if (activeAsset && activeAsset.overlay) {
                const aspectRatio = activeAsset.image.naturalWidth / activeAsset.image.naturalHeight;
                const drawHeight = p.height * p.spriteScale;
                const drawWidth = drawHeight * aspectRatio;
                const drawTopY = hitboxBottomY - drawHeight;
                const drawLeftX = hitboxCenterX - drawWidth / 2;
                const canvasRect = canvas.getBoundingClientRect();

                activeAsset.overlay.style.display = "block";
                activeAsset.overlay.style.left = `${canvasRect.left + drawLeftX}px`;
                activeAsset.overlay.style.top = `${canvasRect.top + drawTopY}px`;
                activeAsset.overlay.style.width = `${drawWidth}px`;
                activeAsset.overlay.style.height = `${drawHeight}px`;
                activeAsset.overlay.style.transform = p.facing === -1 ? "scaleX(-1)" : "scaleX(1)";
            } else {
                context.fillStyle = "#2b6cff";
                context.fillRect(gameLeft + p.x, p.y - cameraY, p.width, p.height);
            }
        },

        drawStatusIndicators(context, cameraY) {
            const p = this.state;
            if (this.isInvulnerable()) {
                context.strokeStyle = "#ffd700";
                context.lineWidth = 3;
                context.strokeRect(
                    gameLeft + p.x - 2,
                    p.y - cameraY - 2,
                    p.width + 4,
                    p.height + 4
                );
            } else if (p.superJumpChargeMs > 0) {
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
    const TerrainSystem = {
        platforms: [],
        enemies: [],
        rocks: [],
        coins: [],

        cameraY: 0,
        startY: 0,
        rockTimer: 0,

        coinCount: 0,
        bestCoins: <?= (int)$high_score ?>,
        score: 0,
        bestScore: <?= (int)$height_reached ?>,
        newRecord: false,
        foodProgress: 0,
        lives: 3,

        platformWidth: 120,
        platformHeight: 20,
        minPlatformStepY: 65,
        maxPlatformStepY: 115,        // comfortably below max regular jump height (~140-165px)
        maxPlatformStepX: 240,

        crumblyChance: 0.28,           // chance a generated platform is crumbly (after initial safe platform)
        crumblyBreakDurationMs: 1500,   // time from first player contact until platform breaks
        crumblyMaxShakePx: 6,          // max horizontal shake offset right before breaking

        enemyMinVerticalSpacing: 300,
        coinChance: 0.6,
        coinRadius: 10,
        
        waterY: 0,
        waterRiseSpeed: 0.55,        // world pixels per 60fps frame (smaller y = higher up)
        waterVisualOffsetY: 0,       // subtle wave/bob offset if desired
        respawnEscapeMarginY: 110, // minimum vertical space above rising water so spider has room to escape

        raindropSprite: (() => {
            const img = new Image();
            const spriteObj = { image: img, loaded: false, scale: 5 };
            img.onload = function() { spriteObj.loaded = true; };
            img.onerror = function() {
                spriteObj.loaded = false;
                console.warn("Raindrop sprite failed to load; using fallback circle.");
            };
            img.src = "images/rain.png";
            return spriteObj;
        })(),

        resetRunState(playerState, fullReset) {
            this.platforms = [];
            this.enemies = [];
            this.rocks = [];
            this.coins = [];
            this.rockTimer = 0;

            if (fullReset) {
                this.lives = playerState.maxLives;
                this.score = 0;
                this.coinCount = 0;
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

        createCoin(platform) {
            this.coins.push({
                x: platform.x + platform.width / 2,
                y: platform.y - this.coinRadius - 6
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
            return true;
        },

        updateCoins(playerState) {
            const foodRequired = playerState.foodToLives || 15;

            this.coins = this.coins.filter((c) => {
                let nx = Math.max(playerState.x, Math.min(c.x, playerState.x + playerState.width));
                let ny = Math.max(playerState.y, Math.min(c.y, playerState.y + playerState.height));

                if (Math.hypot(c.x - nx, c.y - ny) < this.coinRadius) {
                    this.coinCount++;
                    if (this.coinCount > this.bestCoins) {
                        this.bestCoins = this.coinCount;
                        this.newRecord = true;
                        try {
                            localStorage.setItem("platformBestCoins", String(this.bestCoins));
                        } catch (e) {}
                    }

                    // Fill hunger bar up to full; player presses Right-Click when full to convert into +1 life
                    if (this.foodProgress < foodRequired) {
                        this.foodProgress = Math.min(foodRequired, this.foodProgress + 1);
                    }

                    return false;
                }
                return c.y - this.coinRadius < this.waterY;
            });
        },

        createPlatformObject(x, y, forceNormal = false) {
            const isCrumbly = !forceNormal && Math.random() < this.crumblyChance;
            return {
                x: x,
                y: y,
                width: this.platformWidth,
                height: this.platformHeight,
                type: isCrumbly ? "crumbly" : "normal",
                isCrumbly: isCrumbly,
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
                if (Math.random() < this.coinChance) {
                    this.createCoin(p);
                }
            }
        },
        generatePlatforms() {
            let highest = this.platforms.reduce(function(a, b) {
                return a.y < b.y ? a : b;
            });

            while (highest.y > this.cameraY - 200) {
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

                if (Math.random() < this.coinChance) {
                    this.createCoin(highest);
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
        
        createEnemy(y) {
            let size = 30;
            let patrolWidth = 120 + Math.random() * 180;
            let centerX = 50 + Math.random() * (gameWidth - 100);
            let leftLimit = Math.max(size, centerX - patrolWidth / 2);
            let rightLimit = Math.min(gameWidth - size, centerX + patrolWidth / 2);

            this.enemies.push({
                x: leftLimit + Math.random() * (rightLimit - leftLimit),
                y: y,
                size: size,
                speed: 0.5 + Math.random() * 1.5,
                direction: Math.random() < 0.5 ? -1 : 1,
                leftLimit: leftLimit,
                rightLimit: rightLimit
            });
        },
        generateInitialEnemies() {
            this.enemies = [];
            for (let i = 0; i < 8; i++) {
                this.createEnemy(canvas.height - 250 - i * this.enemyMinVerticalSpacing);
            }
        },
        generateEnemies() {
            let highestY = Math.min(...this.enemies.map(function(e) {
                return e.y;
            }));
            while (highestY > this.cameraY - 300) {
                highestY -= this.enemyMinVerticalSpacing + Math.random() * 180;
                this.createEnemy(highestY);
            }

            this.enemies = this.enemies.filter((e) => {
                return e.y < this.cameraY + canvas.height + 200;
            });
        },
        updateEnemies(dt) {
            for (let e of this.enemies) {
                e.x += e.speed * e.direction * dt;
                if (e.x <= e.leftLimit) {
                    e.x = e.leftLimit;
                    e.direction = 1;
                }
                if (e.x >= e.rightLimit) {
                    e.x = e.rightLimit;
                    e.direction = -1;
                }
            }
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
            // 1. Advance rising water upward (decreasing Y)
            this.waterY -= this.waterRiseSpeed * dt;

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
            // Backdrop & central 600px column
            context.fillStyle = "#ffffffff";
            context.fillRect(0, 0, canvas.width, canvas.height);
            context.fillStyle = "#c6c6c6ff";
            context.fillRect(gameLeft, 0, gameWidth, canvas.height);

            // Platforms (normal & crumbly with escalating shake)
            // Platforms (normal & crumbly with escalating shake)
            for (let p of this.platforms) {
                if (p.broken) continue;

                let shakeX = 0;
                let shakeY = 0;

                if (p.isCrumbly && p.crumbleTriggered) {
                    const progress = Math.min(1, p.crumbleElapsedMs / this.crumblyBreakDurationMs);
                    // Ramps up smoothly so shaking gets stronger right before breaking
                    const intensity = progress * progress;
                    const amplitude = intensity * this.crumblyMaxShakePx;
                    shakeX = (Math.random() * 2 - 1) * amplitude;
                    shakeY = (Math.random() * 2 - 1) * (amplitude * 0.35);
                }

                context.fillStyle = p.isCrumbly ? "#854d0e" : "#393939ff";
                context.fillRect(
                    gameLeft + p.x + shakeX,
                    p.y - this.cameraY + shakeY,
                    p.width,
                    p.height
                );

                // Subtle crack/warning accent on crumbly platforms
                if (p.isCrumbly) {
                    context.strokeStyle = p.crumbleTriggered ? "#facc15" : "#ca8a04";
                    context.lineWidth = 2;
                    context.strokeRect(
                        gameLeft + p.x + shakeX + 1,
                        p.y - this.cameraY + shakeY + 1,
                        p.width - 2,
                        p.height - 2
                    );
                }
            }
            
            // Coins / Food
            let t = performance.now() / 200;
            for (let c of this.coins) {
                let bob = Math.sin(t + c.x) * 3;
                context.beginPath();
                context.arc(
                    gameLeft + c.x,
                    c.y - this.cameraY + bob,
                    this.coinRadius,
                    0,
                    Math.PI * 2
                );
                context.fillStyle = "gold";
                context.fill();
                context.lineWidth = 2;
                context.strokeStyle = "#b8860b";
                context.stroke();
            }

            // Enemies
            context.fillStyle = "red";
            for (let e of this.enemies) {
                context.fillRect(
                    gameLeft + e.x - e.size / 2,
                    e.y - this.cameraY - e.size / 2,
                    e.size,
                    e.size
                );
            }

            // Rain droplets
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

            // Rising water hazard inside the 600px game column
            const waterScreenY = this.waterY - this.cameraY;
            if (waterScreenY < canvas.height) {
                const drawTop = Math.max(0, waterScreenY);
                const drawHeight = canvas.height - drawTop;

                context.save();
                context.fillStyle = "rgba(14, 165, 233, 0.55)";
                context.fillRect(gameLeft, drawTop, gameWidth, drawHeight);

                // Crisp water surface line
                context.fillStyle = "#38bdf8";
                context.fillRect(gameLeft, waterScreenY, gameWidth, 4);
                context.restore();
            }
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

        // Ensure extra escape height above rising water if the chosen platform was close to water
        if (PlayerSystem.state.y > safeWaterCeilingY) {
            PlayerSystem.state.y = safeWaterCeilingY;
            PlayerSystem.state.grounded = false;
        }

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
                fetch('saveScore.php', {
                    method: 'POST',
                    body: new URLSearchParams({
                        bestCoins: TerrainSystem.bestCoins,
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
            fetch('saveScore.php', {
                method: 'POST',
                body: new URLSearchParams({
                    bestCoins: TerrainSystem.bestCoins,
                    bestScore: TerrainSystem.bestScore
                })
            })
            .then(res => {
                if (res.ok) TerrainSystem.newRecord = false;
            });
        }
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
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        gameLeft = (canvas.width - gameWidth) / 2;
    });
    document.addEventListener("keydown", function(event) {
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
    canvas.addEventListener("pointerdown", function(event) {
        if (event.button !== 0) return;

        const rect = canvas.getBoundingClientRect();
        const clickX = event.clientX - rect.left;
        const clickY = event.clientY - rect.top;

        if (gameState === "paused") {
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
            if (isPointInRect(clickX, clickY, gameOverButtons.retry)) {
                startFreshRun();
            } else if (isPointInRect(clickX, clickY, gameOverButtons.mainMenu)) {
                window.location.href = "main_menu.php";
            }
            return;
        }

        // Check sidebar Pause button before starting super-jump charge
        if (isPointInRect(clickX, clickY, pauseButton)) {
            pauseGame();
            return;
        }

        PlayerSystem.setSuperJumpHoldInput(true);
    });

    function drawHud() {
        ctx.save();
        ctx.fillStyle = "#111111";
        ctx.font = "bold 20px 'Courier New', Courier, monospace";

        // Right sidebar outside the central 600px column
        const rightSidebarX = Math.min(canvas.width - 16, gameLeft + gameWidth + 20);
        ctx.textAlign = "left";
        ctx.fillText("Lives Left: " + TerrainSystem.lives + " / " + PlayerSystem.state.maxLives, rightSidebarX, 36);
        ctx.fillText("Coins: " + TerrainSystem.coinCount, rightSidebarX, 64);
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

        // Pause button in the right sidebar while playing
        if (gameState === "playing") {
            pauseButton.x = rightSidebarX;
            pauseButton.y = 158;

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
        ctx.fillStyle = "#ffffff";
        ctx.font = "bold 36px 'Courier New', Courier, monospace";
        ctx.fillText("PAUSED", centerX, panelTop);

        ctx.font = "bold 18px 'Courier New', Courier, monospace";
        ctx.fillStyle = "#e2e8f0";
        ctx.fillText("Height: " + TerrainSystem.score + "  |  Coins: " + TerrainSystem.coinCount, centerX, panelTop + 42);

        // Resume button
        pauseButtons.resume.x = centerX - pauseButtons.resume.width / 2;
        pauseButtons.resume.y = panelTop + 74;

        ctx.fillStyle = "#22c55e";
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

        ctx.fillStyle = "#f59e0b";
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

        ctx.fillStyle = "#3b82f6";
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
        ctx.fillStyle = "#ffffff";
        ctx.font = "bold 36px 'Courier New', Courier, monospace";
        ctx.fillText("GAME OVER", centerX, panelTop);

        ctx.font = "bold 20px 'Courier New', Courier, monospace";
        ctx.fillStyle = "#e2e8f0";
        ctx.fillText("Height Climbed: " + TerrainSystem.score, centerX, panelTop + 44);
        ctx.fillText("Highest Score: " + TerrainSystem.bestScore, centerX, panelTop + 74);
        ctx.fillText("Food Collected: " + TerrainSystem.coinCount, centerX, panelTop + 104);

        gameOverButtons.retry.x = centerX - gameOverButtons.retry.width / 2;
        gameOverButtons.retry.y = panelTop + 136;

        ctx.fillStyle = "#22c55e";
        ctx.fillRect(
            gameOverButtons.retry.x,
            gameOverButtons.retry.y,
            gameOverButtons.retry.width,
            gameOverButtons.retry.height
        );
        ctx.strokeStyle = "#ffffff";
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

        ctx.fillStyle = "#3b82f6";
        ctx.fillRect(
            gameOverButtons.mainMenu.x,
            gameOverButtons.mainMenu.y,
            gameOverButtons.mainMenu.width,
            gameOverButtons.mainMenu.height
        );
        ctx.strokeStyle = "#ffffff";
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
        const hideSprite = gameState === "gameover" || gameState === "paused";
        TerrainSystem.draw(ctx);
        PlayerSystem.drawSprite(ctx, TerrainSystem.cameraY, hideSprite);
        drawHud();
        PlayerSystem.drawStatusIndicators(ctx, TerrainSystem.cameraY);
        drawPauseOverlay();
        drawGameOverOverlay();
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
                TerrainSystem.updateEnemies(dt);
                TerrainSystem.updateRocks(dt);
                TerrainSystem.updateCoins(PlayerSystem.state);
                PlayerSystem.checkEnemyCollision(TerrainSystem.enemies, deductLifeOnly, triggerGameOver);
                PlayerSystem.checkRockCollision(TerrainSystem.rocks, deductLifeOnly, triggerGameOver);
            }
        }

        draw();
        PlayerSystem.consumeFrameEdgeInputs();
        requestAnimationFrame(gameLoop);
    }

    startFreshRun();
    requestAnimationFrame(gameLoop);
//#endregion

</script>

</body>
</html>
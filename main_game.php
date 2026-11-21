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
    // ==========================================
    // CANVAS & SESSION STATE
    // ==========================================
    const canvas = document.getElementById("gameCanvas");
    const ctx = canvas.getContext("2d");

    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
    const gameWidth = 600;
    let gameLeft = (canvas.width - gameWidth) / 2;
    let gameState = "playing"; // "playing" | "gameover"
    const gameOverButtons = {
        retry:    { x: 0, y: 0, width: 180, height: 48 },
        mainMenu: { x: 0, y: 0, width: 180, height: 48 }
    };

    // ==========================================
    // REUSABLE SPRITE HELPERS & ASSETS
    // ==========================================
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

    // Player & hazard sprite assets
    const spiderIdleAsset = createSpriteAsset(
        "images/dante.gif",
        "spiderGifOverlay",
        "Spider sprite failed to load; using fallback box."
    );
    const spiderWalkAsset = createSpriteAsset(
        "images/dante_walk.gif",
        "spiderWalkGifOverlay",
        "Spider walk sprite failed to load; using idle spider sprite."
    );
    const spiderChargeAsset = createSpriteAsset(
        "images/dante_charge.gif",
        "spiderChargeGifOverlay",
        "Spider charge sprite failed to load; using idle spider sprite."
    );
    const allPlayerAssets = [spiderIdleAsset, spiderChargeAsset, spiderWalkAsset];

    // ==========================================
    // RAINDROPLET SPRITE SETUP
    // ==========================================
    const raindropSprite = new Image();
    let raindropSpriteLoaded = false;
    
    raindropSprite.onload = function() {
        raindropSpriteLoaded = true;
    };
    raindropSprite.onerror = function() {
        raindropSpriteLoaded = false;
        console.warn("Raindrop sprite failed to load; using fallback circle.");
    };

    // Update this path to match your raindroplet sprite file
    raindropSprite.src = "images/rain.png";
    const raindropSpriteScale = 5; // visual scale multiplier relative to r.size

    //Play Attributes
    const player = {
        //size
        x: 0,
        y: 0,
        width: 40,
        height: 20,
        facing: 1, // 1 = right, -1 = left

        //visual sprite sizing (does NOT affect hitbox or collisions)
        spriteScale: 1.25, // adjust up/down to taste while keeping proportions natural
        
        //velocity
        velocityX: 0,
        velocityY: 0,
        
        //attributes
        speed: 2.5,
        jumpPower: 10,
        smallJumpPower: 7.5,          // initial small jump launch speed
        peakExtendThreshold: 4,       // when upward velocity slows near peak
        peakGravityScale: 0.35,
        gravity: 0.2,
        grounded: false,
        canExtendJump: false,

        dropThroughFrames: 10,
        dropThroughTimer: 0,
        ignorePlatform: null,

        superJumpPowerScale: Math.SQRT2,
        superJumpChargeMs: 0,          // accumulated hold time in ms
        superJumpChargeRequiredMs: 1500,
        isSuperJumping: false,         // invulnerable while true until landing
    };


// ==========================================
// CONTROLS & BUTTON STATE FUNCTIONS
// ==========================================

//Button Function Declerations
    const keys = {
        left: false,
        right: false,
        dropDown: false,
        dropDownPressed: false,
        jump: false,
        jumpPressed: false,
        jumpHeld: false,
        superJumpHeld: false,
        superJumpTriggered: false
    };
    const jumpInputConfig = {
        doubleTapWindowMs: 250,          // max ms between taps to count as a double-tap
        doubleTapSuperJumpEnabled: true  // to be simplified/cleaned up after these controls are integrated
    };
    let lastJumpTapTime = 0;
//endregion

//State Boolean Controller
    function setLeftInput(active) {
        keys.left = Boolean(active);
    }
    function setRightInput(active) {
        keys.right = Boolean(active);
    }
    function setDropDownInput(active) {
        const isNowActive = Boolean(active);
        if (isNowActive && !keys.dropDown) {
            keys.dropDownPressed = true;
        }
        keys.dropDown = isNowActive;
    }
    function setJumpInput(active) {
        const isNowActive = Boolean(active);

        if (isNowActive) {
            if (!keys.jumpHeld) {
                const now = performance.now();
                const isDoubleTap =
                    jumpInputConfig.doubleTapSuperJumpEnabled &&
                    (now - lastJumpTapTime) <= jumpInputConfig.doubleTapWindowMs;

                keys.jumpPressed = true;
                if (isDoubleTap) {
                    keys.superJumpTriggered = true;
                    lastJumpTapTime = 0; // reset so a third tap doesn't immediately chain
                } else {
                    lastJumpTapTime = now;
                }
            }
            keys.jump = true;
            keys.jumpHeld = true;
        } else {
            keys.jump = false;
            keys.jumpHeld = false;
        }
    }
    function setSuperJumpHoldInput(active) {
        keys.superJumpHeld = Boolean(active);
    }
    function triggerSuperJumpButton() {
        keys.superJumpTriggered = true;
    }
    function setDoubleTapSuperJump(enabled) {
        jumpInputConfig.doubleTapSuperJumpEnabled = Boolean(enabled);
    }
//endregion

//Button Press Processors and Helpers
    function clearAllInputs() {
        keys.left = false;
        keys.right = false;
        keys.dropDown = false;
        keys.dropDownPressed = false;
        keys.jump = false;
        keys.jumpPressed = false;
        keys.jumpHeld = false;
        keys.superJumpHeld = false;
        keys.superJumpTriggered = false;
        lastJumpTapTime = 0;
        jumpPressFlashUntil = 0;
        clickPressFlashUntil = 0;
    }
    function handleKeyInput(event, isDown) {
        const k = event.key.toLowerCase();

        if (k === "a" || k === "arrowleft") {
            setLeftInput(isDown);
        }
        if (k === "d" || k === "arrowright") {
            setRightInput(isDown);
        }
        if (k === "s" || k === "arrowdown") {
            setDropDownInput(isDown);
        }
        if (event.code === "Space" || k === "w" || k === "arrowup") {
            if (isDown) {
                setJumpInput(true);
            } else {
                setJumpInput(false);
            }
            if (event.code === "Space") {
                event.preventDefault(); // stop Space from scrolling the page
            }
        }
    }
    document.addEventListener("keydown", function(event) {
        handleKeyInput(event, true);
    });
    document.addEventListener("keyup", function(event) {
        handleKeyInput(event, false);
    });
    canvas.addEventListener("pointerdown", function(event) {
        if (event.button === 0) { // left click / primary pointer press
            setSuperJumpHoldInput(true);
        }
    });
    window.addEventListener("pointerup", function(event) {
        if (event.button === 0) {
            setSuperJumpHoldInput(false);
        }
    });
    window.addEventListener("pointercancel", function() {
        setSuperJumpHoldInput(false);
    });
    window.addEventListener("blur", function() {
        clearAllInputs();
    });
//endregion

// ==========================================
// INPUT DEBUG GIZMOS (5-BOX VISUALIZER)
// ==========================================
    const inputGizmoConfig = {
        enabled: true,
        boxWidth: 74,
        boxHeight: 34,
        gap: 8,
        offsetX: 12,
        offsetY: 72, // positioned below the HUD text on the left
        pressFlashMs: 120,       // how long "Green (pressed)" shows before "Orange (held)"
        idleColor: "#22262e",
        pressedColor: "#22c55e", // Green on press
        heldColor: "#f59e0b",    // Orange while held
        borderColor: "#cbd5e1",
        textColor: "#ffffff"
    };

    let jumpPressFlashUntil = 0;
    let clickPressFlashUntil = 0;
    let prevJumpActive = false;
    let prevClickActive = false;

    function getGizmoBoxColor(boxKey) {
        const now = performance.now();

        if (boxKey === "jump") {
            const isJumpActive = Boolean(keys.jumpHeld || keys.jump);
            if ((isJumpActive && !prevJumpActive) || keys.jumpPressed || keys.superJumpTriggered) {
                jumpPressFlashUntil = now + inputGizmoConfig.pressFlashMs;
            }
            prevJumpActive = isJumpActive;

            if (now < jumpPressFlashUntil) return inputGizmoConfig.pressedColor;
            if (isJumpActive) return inputGizmoConfig.heldColor;
            return inputGizmoConfig.idleColor;
        }

        if (boxKey === "click") {
            if (keys.superJumpTriggered) return inputGizmoConfig.pressedColor;
            if (keys.superJumpHeld) return inputGizmoConfig.heldColor;
            return inputGizmoConfig.idleColor;
        }

        if (boxKey === "left" && keys.left) return inputGizmoConfig.pressedColor;
        if (boxKey === "right" && keys.right) return inputGizmoConfig.pressedColor;
        if (boxKey === "down" && keys.dropDown) return inputGizmoConfig.pressedColor;

        return inputGizmoConfig.idleColor;
    }

    function drawInputGizmos(context) {
        if (!inputGizmoConfig.enabled) return;

        const boxes = [
            { id: "jump",  label: "Jump"  },
            { id: "left",  label: "Left"  },
            { id: "right", label: "Right" },
            { id: "down",  label: "Down"  },
            { id: "click", label: "Click" }
        ];

        const startX = gameLeft + inputGizmoConfig.offsetX;
        const startY = inputGizmoConfig.offsetY;

        context.save();
        context.font = "bold 13px 'Courier New', Courier, monospace";
        context.textAlign = "center";
        context.textBaseline = "middle";

        boxes.forEach((box, index) => {
            const x = startX + index * (inputGizmoConfig.boxWidth + inputGizmoConfig.gap);
            const y = startY;
            const fillColor = getGizmoBoxColor(box.id);

            context.fillStyle = fillColor;
            context.fillRect(x, y, inputGizmoConfig.boxWidth, inputGizmoConfig.boxHeight);

            context.lineWidth = 2;
            context.strokeStyle = inputGizmoConfig.borderColor;
            context.strokeRect(x, y, inputGizmoConfig.boxWidth, inputGizmoConfig.boxHeight);

            context.fillStyle = inputGizmoConfig.textColor;
            context.fillText(
                box.label,
                x + inputGizmoConfig.boxWidth / 2,
                y + inputGizmoConfig.boxHeight / 2
            );
        });

        context.restore();
    }
//endregion

    //world attributes
    let platforms = [];
    let enemies = [];
    let rocks = [];
    //for cam
    let cameraY = 0;
    let startY = 0;
    //for coins
    let coins = [];
    let coinCount = 0;
    let bestCoins = 0;
    //for score and rocktimer
    let score = 0;
    let bestScore = 0;
    let rockTimer = 0;
    let newRecord = false; 
    let lives = 4;
    //for platform and enemy spacing
    const platformWidth = 120;
    const platformHeight = 20;
    const enemyMinVerticalSpacing = 300;

    bestCoins = <?= (int)$high_score?>;
    bestScore = <?=  (int)$height_reached?>;


    //coins
    const COIN_CHANCE = 0.6;
    const coinRadius = 10;


    function createCoin(platform) {
        coins.push({
            x: platform.x + platform.width / 2,
            y: platform.y - coinRadius - 6

        });
    }
    function updateCoins() {
        coins = coins.filter(function(c) {
            // Distance from the coin's center to the nearest point of the player's box.
            let nx = Math.max(player.x, Math.min(c.x, player.x + player.width));
            let ny = Math.max(player.y, Math.min(c.y, player.y + player.height));

            if (Math.hypot(c.x - nx, c.y - ny) < coinRadius) { 
                coinCount++;
                if (coinCount > bestCoins) {
                    bestCoins = coinCount;
                    newRecord = true;
                    try {
                        localStorage.setItem("platformBestCoins", String(bestCoins)); //storage system for coins
                    } catch (e) {}
                }
                return false;
            }
            return c.y < cameraY + canvas.height + 200;
        });
    }

    //create initial platform
    function createInitialPlatforms() {
        platforms = [{
            x: gameWidth / 2 - 60,
            y: canvas.height - 100,
            width: platformWidth,
            height: platformHeight
        }];
        for (let i = 1; i < 10; i++) {
            let p = {
                x: Math.random() * (gameWidth - platformWidth),
                y: canvas.height - 100 - i * 100,
                width: platformWidth,
                height: platformHeight
            };

            platforms.push(p);
            if (Math.random() < COIN_CHANCE) {
                createCoin(p);
            }
        }
    }

    //generate new platforms
    function generatePlatforms() {
        let highest = platforms.reduce(function(a, b) {
            return a.y < b.y ? a : b;
        });
        while (highest.y > cameraY - 200) {
            highest = {
                x: Math.random() * (gameWidth - platformWidth),
                y: highest.y - (80 + Math.random() * 70),
                width: platformWidth,
                height: platformHeight
            };
            platforms.push(highest);
            if (Math.random() < COIN_CHANCE) {
                createCoin(highest);
            }
        }
    }

    //enemy
    function createEnemy(y) {
        let size = 30;
        let patrolWidth = 120 + Math.random() * 180;
        let centerX = 50 + Math.random() * (gameWidth - 100);
        let leftLimit = Math.max(
            size,
            centerX - patrolWidth / 2
        );
        let rightLimit = Math.min(
            gameWidth - size,
            centerX + patrolWidth / 2
        );

        enemies.push({
            x: leftLimit + Math.random() * (rightLimit - leftLimit),
            y: y,
            size: size,
            //enemy attributes and directions
            speed: 0.5 + Math.random() * 1.5,
            direction: Math.random() < 0.5 ? -1 : 1,
            leftLimit: leftLimit,
            rightLimit: rightLimit
        });
    }

    //generate initial enemies
    function generateInitialEnemies() {
        enemies = [];
        for (let i = 0; i < 8; i++) {
            createEnemy(
                canvas.height - 250 - i * enemyMinVerticalSpacing
            );
        }
    }

    //generate new enemies
    function generateEnemies() {
        let highestY = Math.min(...enemies.map(function(e) {
            return e.y;
        }));
        while (highestY > cameraY - 300) {
            highestY -= enemyMinVerticalSpacing + Math.random() * 180;
            createEnemy(highestY);
        }

        enemies = enemies.filter(function(e) {
            return e.y < cameraY + canvas.height + 200;
        });

    }

    function updateEnemies(dt) {
        for (let e of enemies) {
            e.x += e.speed * e.direction * dt;
            if (e.x <= e.leftLimit) {
                e.x = e.leftLimit;
                e.direction = 1;
            } if (e.x >= e.rightLimit) {
                e.x = e.rightLimit;
                e.direction = -1;
            }
        }
        generateEnemies();
    }

    //rocks
    function createRock() {
        let rock = {
            x: Math.random() * gameWidth,
            y: cameraY - 50,
            size: Math.random() * 8 + 8,
            speed: Math.random() * 1.5 + 1
        };
        rocks.push(rock);
    }

    //rock generation
    function updateRocks(dt) {
        rockTimer += dt;
        if (rockTimer >= 180) { // 1 rock per 1 sec
            rockTimer = 0;
            createRock();
        }
        for (let r of rocks) { //rock speed
            r.y += r.speed * dt;
        }
        rocks = rocks.filter(function(r) {
            return r.y - r.size < cameraY + canvas.height + 200;
        });
    }

// ==========================================
// PLAYER UPDATE & SUBSYSTEMS
// ==========================================
    function updateHorizontalMovement(dt) {
        const isChargingSuperJump = keys.superJumpHeld && !player.isSuperJumping;

        // Allow horizontal movement while airborne during charge; lock once grounded
        if (isChargingSuperJump && player.grounded) {
            player.velocityX = 0;
            return;
        }

        if (keys.left) {
            player.velocityX = -player.speed;
            player.facing = -1;
        } else if (keys.right) {
            player.velocityX = player.speed;
            player.facing = 1;
        } else {
            player.velocityX = 0;
        }

        player.x += player.velocityX * dt;
        player.x = Math.max(0, Math.min(gameWidth - player.width, player.x));
    }
    function updateVerticalMotion(dt) {
        const frameMs = dt * (1000 / 60);
        const isChargingSuperJump = keys.superJumpHeld && !player.isSuperJumping;

        // Charge Super Jump while Click is held; releasing early resets charge to 0
        if (isChargingSuperJump) {
            player.superJumpChargeMs += frameMs;

            if (player.superJumpChargeMs >= player.superJumpChargeRequiredMs) {
                player.superJumpChargeMs = 0;
                player.isSuperJumping = true;
                player.grounded = false;
                player.canExtendJump = false;
                player.ignorePlatform = null;
                player.dropThroughTimer = 0;
                player.velocityY = -(player.jumpPower * player.superJumpPowerScale);
            }
        } else {
            player.superJumpChargeMs = 0;
        }

        // Initial jump press starts with a small jump (blocked while charging or mid-super-jump)
        if (!isChargingSuperJump && !player.isSuperJumping && keys.jumpPressed && player.grounded) {
            player.velocityY = -player.smallJumpPower;
            player.grounded = false;
            player.canExtendJump = true;
            player.ignorePlatform = null;
            player.dropThroughTimer = 0;
        }

        // Stop peak extension if jump is released, charging starts, or player begins falling
        if (isChargingSuperJump || !keys.jumpHeld || player.velocityY > 0) {
            player.canExtendJump = false;
        }

        // Reduce gravity near peak while holding jump to climb toward regular jump height
        let effectiveGravity = player.gravity;
        if (
            !isChargingSuperJump &&
            !player.isSuperJumping &&
            keys.jumpHeld &&
            player.canExtendJump &&
            player.velocityY < 0 &&
            player.velocityY >= -player.peakExtendThreshold
        ) {
            effectiveGravity = player.gravity * player.peakGravityScale;
        }

        player.velocityY += effectiveGravity * dt;
        player.y += player.velocityY * dt;
    }
    function resolvePlatformCollisions(prevBottom) {
        const isChargingSuperJump = keys.superJumpHeld && !player.isSuperJumping;

        if (player.dropThroughTimer > 0) {
            player.dropThroughTimer = Math.max(0, player.dropThroughTimer - 1);
            if (player.dropThroughTimer === 0) {
                player.ignorePlatform = null;
            }
        }

        player.grounded = false;

        // Land on a platform only when falling through its top edge
        if (player.velocityY >= 0) {
            const bottom = player.y + player.height;
            for (const p of platforms) {
                if (p === player.ignorePlatform && player.dropThroughTimer > 0) {
                    continue;
                }

                const withinHorizontal =
                    player.x < p.x + p.width &&
                    player.x + player.width > p.x;
                const crossedTop = prevBottom <= p.y && bottom >= p.y;

                if (withinHorizontal && crossedTop) {
                    // Drop down only once per fresh press, and never while charging Super Jump
                    if (!isChargingSuperJump && keys.dropDownPressed) {
                        keys.dropDownPressed = false;
                        player.ignorePlatform = p;
                        player.dropThroughTimer = player.dropThroughFrames;
                        player.canExtendJump = false;
                        break;
                    }

                    player.y = p.y - player.height;
                    player.velocityY = 0;
                    player.grounded = true;
                    player.isSuperJumping = false;
                    player.ignorePlatform = null;
                    player.dropThroughTimer = 0;
                    break;
                }
            }
        }
    }
    function updateCameraAndScoring() {
        // Camera follows upward progress
        cameraY = Math.min(cameraY, player.y - canvas.height / 2);
        generatePlatforms();
        platforms = platforms.filter(function(p) {
            return p.y < cameraY + canvas.height + 200;
        });

        // Update height score & best record
        score = Math.max(score, Math.floor((startY - player.y) / 10));
        if (score > bestScore) {
            bestScore = score;
            newRecord = true;
            try {
                localStorage.setItem("platformBestScore", String(bestScore));
            } catch (e) {}
        }

        if (player.y > cameraY + canvas.height) {
            resetGame();
        }
    }
    function updatePlayer(dt) {
        const prevBottom = player.y + player.height;

        updateHorizontalMovement(dt);
        updateVerticalMotion(dt);
        resolvePlatformCollisions(prevBottom);
        updateCameraAndScoring();
    }

//Enemy Collission
    function checkEnemyCollision() {
        if (player.isSuperJumping) return;

        for (let e of enemies) {
            let half = e.size / 2;
            if (
                player.x < e.x + half &&
                player.x + player.width > e.x - half &&
                player.y < e.y + half &&
                player.y + player.height > e.y - half
            ) {
                resetGame();
                return;
            }
        }
    }
//endregion

//Rock collision
    function checkRockCollision() {
        if (player.isSuperJumping) return;

        let cx = player.x + player.width / 2;
        let cy = player.y + player.height / 2;
        let playerRadius = Math.max(
            player.width,
            player.height
        ) / 2;
        for (let r of rocks) {
            if (Math.hypot(cx - r.x, cy - r.y) < playerRadius + r.size) {
                resetGame();
                return;
            }
        }
    }
//endregion

    //reset func and save score
    // as long as the lives aren't 0, the player's last height and coins collected are saved, then they're reset
    // only saves the score and height once all lives are depleted
        
    function startFreshRun() {
        clearAllInputs();
        lives = 4;
        score = 0;
        coinCount = 0;
        newRecord = false;
        gameState = "playing";

        player.x = gameWidth / 2 - player.width / 2;
        player.y = canvas.height - 150;

        player.velocityX = 0;
        player.velocityY = 0;
        player.grounded = false;
        player.facing = 1;
        player.canExtendJump = false;
        player.dropThroughTimer = 0;
        player.ignorePlatform = null;
        player.superJumpChargeMs = 0;
        player.isSuperJumping = false;

        startY = player.y;
           cameraY = player.y - canvas.height / 2;
        rockTimer = 0;

        createInitialPlatforms();
        generateInitialEnemies();

        rocks = [];
        coins = [];
    }
    function resetGame() {
        clearAllInputs();
        lives -= 1;

        if (lives <= 0) {
            lives = 0;
            gameState = "gameover";

            if (newRecord) {
                fetch('saveScore.php', {
                    method: 'POST',
                    body: new URLSearchParams({ bestCoins, bestScore })
                })
                .then(res => {
                    if (res.ok) newRecord = false;
                });
            }
            return;
        }

        player.x = gameWidth / 2 - player.width / 2;
        player.y = canvas.height - 150;

        player.velocityX = 0;
        player.velocityY = 0;
        player.grounded = false;
        player.facing = 1;
        player.canExtendJump = false;
        player.dropThroughTimer = 0;
        player.ignorePlatform = null;
        player.superJumpChargeMs = 0;
        player.isSuperJumping = false;

        startY = player.y;
        cameraY = player.y - canvas.height / 2;
        rockTimer = 0;

        createInitialPlatforms();
        generateInitialEnemies();

        rocks = [];
        coins = [];
    }
    function isPointInRect(px, py, rect) {
        return (
            px >= rect.x &&
            px <= rect.x + rect.width &&
            py >= rect.y &&
            py <= rect.y + rect.height
        );
    }
    canvas.addEventListener("pointerdown", function(event) {
        if (event.button !== 0) return;

        if (gameState === "gameover") {
            const rect = canvas.getBoundingClientRect();
            const clickX = event.clientX - rect.left;
            const clickY = event.clientY - rect.top;

            if (isPointInRect(clickX, clickY, gameOverButtons.retry)) {
                startFreshRun();
            } else if (isPointInRect(clickX, clickY, gameOverButtons.mainMenu)) {
                window.location.href = "main_menu.php"; // change if your main menu uses another file
            }
            return;
        }

        setSuperJumpHoldInput(true);
    });

    // ==========================================
    // PLAYER SPRITE RENDER FUNCTION
    // ==========================================
    let wasGroundedChargingSuperJump = false;
    let wasWalking = false;

    function selectActivePlayerAsset() {
        const isGroundedChargingSuperJump =
            keys.superJumpHeld && !player.isSuperJumping && player.grounded;

        if (isGroundedChargingSuperJump && isSpriteReady(spiderChargeAsset)) {
            return spiderChargeAsset;
        }

        const isMovingHorizontally =
            !isGroundedChargingSuperJump &&
            (keys.left || keys.right || Math.abs(player.velocityX) > 0);

        if (isMovingHorizontally && isSpriteReady(spiderWalkAsset)) {
            return spiderWalkAsset;
        }

        if (isSpriteReady(spiderIdleAsset)) {
            return spiderIdleAsset;
        }
        return null;
    }
    function drawPlayerSprite() {
        const isGroundedChargingSuperJump =
            gameState !== "gameover" &&
            keys.superJumpHeld &&
            !player.isSuperJumping &&
            player.grounded;

        const isWalking =
            gameState !== "gameover" &&
            !isGroundedChargingSuperJump &&
            (keys.left || keys.right || Math.abs(player.velocityX) > 0);

        if (isGroundedChargingSuperJump && !wasGroundedChargingSuperJump) {
            restartGifOverlay(spiderChargeAsset);
        } else if (!isGroundedChargingSuperJump && wasGroundedChargingSuperJump) {
            hideSpriteOverlay(spiderChargeAsset);
        }
        wasGroundedChargingSuperJump = isGroundedChargingSuperJump;

        if (isWalking && !wasWalking) {
            restartGifOverlay(spiderWalkAsset);
        } else if (!isWalking && wasWalking) {
            hideSpriteOverlay(spiderWalkAsset);
        }
        wasWalking = isWalking;

        const activeAsset = gameState !== "gameover" ? selectActivePlayerAsset() : null;
        const hitboxCenterX = gameLeft + player.x + player.width / 2;
        const hitboxBottomY = player.y - cameraY + player.height;

        for (const asset of allPlayerAssets) {
            if (asset !== activeAsset) {
                hideSpriteOverlay(asset);
            }
        }

        if (gameState === "gameover") {
            wasGroundedChargingSuperJump = false;
            return;
        }

        if (activeAsset && activeAsset.overlay) {
            const aspectRatio = activeAsset.image.naturalWidth / activeAsset.image.naturalHeight;
            const drawHeight = player.height * player.spriteScale;
            const drawWidth = drawHeight * aspectRatio;
            const drawTopY = hitboxBottomY - drawHeight;
            const drawLeftX = hitboxCenterX - drawWidth / 2;
            const canvasRect = canvas.getBoundingClientRect();

            activeAsset.overlay.style.display = "block";
            activeAsset.overlay.style.left = `${canvasRect.left + drawLeftX}px`;
            activeAsset.overlay.style.top = `${canvasRect.top + drawTopY}px`;
            activeAsset.overlay.style.width = `${drawWidth}px`;
            activeAsset.overlay.style.height = `${drawHeight}px`;
            activeAsset.overlay.style.transform = player.facing === -1 ? "scaleX(-1)" : "scaleX(1)";
        } else {
            ctx.fillStyle = "#2b6cff";
            ctx.fillRect(
                gameLeft + player.x,
                player.y - cameraY,
                player.width,
                player.height
            );
        }
    }
    function restartGifOverlay(asset) {
        if (!asset || !asset.overlay) return;
        const baseSrc = asset.image.src;
        // Reset src briefly so the browser restarts the GIF animation from frame 1
        asset.overlay.src = "";
        asset.overlay.src = baseSrc;
    }

    //draw
    function draw() {
        ctx.fillStyle = "#ffffffff"; //background 
        ctx.fillRect(
            0,
            0,
            canvas.width,
            canvas.height
        );
        ctx.fillStyle = "#c6c6c6ff"; //game area
        ctx.fillRect(
            gameLeft,
            0,
            gameWidth,
            canvas.height
        );

        ctx.fillStyle = "#393939ff"; //paltform 
        for (let p of platforms) {
            ctx.fillRect(
                gameLeft + p.x,
                p.y - cameraY,
                p.width,
                p.height
            );
        }

        let t = performance.now() / 200; //coin
        for (let c of coins) {
            let bob = Math.sin(t + c.x) * 3;
            ctx.beginPath();
            ctx.arc(
                gameLeft + c.x,
                c.y - cameraY + bob,
                coinRadius,
                0,
                Math.PI * 2
            );
            ctx.fillStyle = "gold";
            ctx.fill();
            ctx.lineWidth = 2;
            ctx.strokeStyle = "#b8860b";
            ctx.stroke();
        }

        ctx.fillStyle = "red"; //enemy
        for (let e of enemies) {
            ctx.fillRect(
                gameLeft + e.x - e.size / 2,
                e.y - cameraY - e.size / 2,
                e.size,
                e.size
            );
        }

        // player (spider sprite with fallback box)
        drawPlayerSprite();
        
        // raindrops (sprite with fallback circle)
        for (let r of rocks) {
            const dropCenterX = gameLeft + r.x;
            const dropCenterY = r.y - cameraY;
            const baseDiameter = r.size * 2;

            if (raindropSpriteLoaded && raindropSprite.naturalWidth > 0 && raindropSprite.naturalHeight > 0) {
                const aspectRatio = raindropSprite.naturalWidth / raindropSprite.naturalHeight;
                const drawHeight = baseDiameter * raindropSpriteScale;
                const drawWidth = drawHeight * aspectRatio;

                ctx.save();
                ctx.imageSmoothingEnabled = false;
                ctx.drawImage(
                    raindropSprite,
                    dropCenterX - drawWidth / 2,
                    dropCenterY - drawHeight / 2,
                    drawWidth,
                    drawHeight
                );
                ctx.restore();
            } else {
                ctx.beginPath();
                ctx.arc(
                    dropCenterX,
                    dropCenterY,
                    r.size,
                    0,
                    Math.PI * 2
                );
                ctx.fillStyle = "#555555";
                ctx.fill();
            }
        }

        // ==========================================
        // HUD / SCORE DISPLAY (OUTSIDE PORTRAIT COLUMN)
        // ==========================================
        ctx.save();
        ctx.fillStyle = "#111111";
        ctx.font = "bold 20px 'Courier New', Courier, monospace";

        // LEFT SIDEBAR (outside the 600px column)
        const leftSidebarX = Math.max(16, gameLeft - 220); // falls back gracefully on smaller screens
        ctx.textAlign = "left";

        // RIGHT SIDEBAR (outside the 600px column)
        const rightSidebarX = Math.min(canvas.width - 16, gameLeft + gameWidth + 20);
        ctx.textAlign = "left";
        ctx.fillText("Lives Left: " + lives, rightSidebarX, 36);
        ctx.fillText("Coins: " + coinCount, rightSidebarX, 64);
        ctx.fillText("Height: " + score, rightSidebarX, 92);

        ctx.restore();

        if (player.isSuperJumping) {
            ctx.strokeStyle = "#ffd700";
            ctx.lineWidth = 3;
            ctx.strokeRect(
                gameLeft + player.x - 2,
                player.y - cameraY - 2,
                player.width + 4,
                player.height + 4
            );
        } else if (player.superJumpChargeMs > 0) {
            const chargeRatio = Math.min(1, player.superJumpChargeMs / player.superJumpChargeRequiredMs);
            const barWidth = player.width;
            const barHeight = 6;
            const barX = gameLeft + player.x;
            const barY = player.y - cameraY - 12;

            ctx.fillStyle = "#222";
            ctx.fillRect(barX, barY, barWidth, barHeight);
            ctx.fillStyle = "#22c55e";
            ctx.fillRect(barX, barY, barWidth * chargeRatio, barHeight);
        }

        if (gameState === "gameover") {
            // Dim background over the 600px game column
            ctx.fillStyle = "rgba(15, 23, 42, 0.78)";
            ctx.fillRect(gameLeft, 0, gameWidth, canvas.height);

            const centerX = gameLeft + gameWidth / 2;
            const panelTop = canvas.height / 2 - 120;

            ctx.textAlign = "center";
            ctx.fillStyle = "#ffffff";
            ctx.font = "bold 36px 'Courier New', Courier, monospace";
            ctx.fillText("GAME OVER", centerX, panelTop);

            ctx.font = "bold 20px 'Courier New', Courier, monospace";
            ctx.fillStyle = "#e2e8f0";
            ctx.fillText("Height: " + score, centerX, panelTop + 44);
            ctx.fillText("Coins Collected: " + coinCount, centerX, panelTop + 74);

            // Retry button
            gameOverButtons.retry.x = centerX - gameOverButtons.retry.width / 2;
            gameOverButtons.retry.y = panelTop + 108;

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

            // Main Menu button
            gameOverButtons.mainMenu.x = centerX - gameOverButtons.mainMenu.width / 2;
            gameOverButtons.mainMenu.y = panelTop + 170;

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
        //drawInputGizmos(ctx);


    }

    //main loop
    let lastTime = performance.now();
    function gameLoop(now) {

        let dt = Math.min((now - lastTime) / (1000 / 60), 3); // 1 = one 60fps frame
        lastTime = now;
        if (gameState === "playing") {
            updatePlayer(dt);
            updateEnemies(dt);
            updateRocks(dt);
            updateCoins();
            checkEnemyCollision();
            checkRockCollision();
        }
        draw();
        keys.jumpPressed = false;
        keys.dropDownPressed = false;
        keys.superJumpTriggered = false;
        requestAnimationFrame(gameLoop);
    }

    window.addEventListener("resize", function() {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        gameLeft = (canvas.width - gameWidth) / 2;
    });
    
    resetGame();
    requestAnimationFrame(gameLoop);

</script>

</body>
</html>
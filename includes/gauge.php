<?php $cap = capacity_info(); ?>
<div class="text-center">
    <div class="gauge" data-gauge>
        <svg viewBox="0 0 200 200" width="100%" height="100%">
            <circle class="gauge-bg" cx="100" cy="100" r="85" fill="none" stroke-width="16"/>
            <circle class="gauge-fg" cx="100" cy="100" r="85" fill="none" stroke-width="16"/>
        </svg>
        <div class="gauge-text">
            <div class="num" data-cap-inside><?= $cap['inside'] ?></div>
            <div class="of">of <span data-cap-max><?= $cap['capacity'] ?></span> max</div>
        </div>
    </div>
    <div class="mt-3"><span data-cap-label class="badge bg-<?= e($cap['color']) ?>"><?= e($cap['label']) ?></span>
        <span class="text-secondary small ms-1"><span data-cap-percent><?= $cap['percent'] ?>%</span> full</span></div>
</div>

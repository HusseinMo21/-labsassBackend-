<?php $__currentLoopData = $groupedEndpoints; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <h1 id="<?php echo Str::slug($group['name']); ?>"><?php echo $group['name']; ?></h1>

    <?php echo Parsedown::instance()->text($group['description']); ?>


    <?php $__currentLoopData = $group['subgroups']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $subgroupName => $subgroup): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php if($subgroupName !== ""): ?>
            <h2 id="<?php echo Str::slug($group['name']); ?>-<?php echo Str::slug($subgroupName); ?>"><?php echo e($subgroupName); ?></h2>
            <?php ($subgroupDescription = collect($subgroup)->first(fn ($e) => $e->metadata->subgroupDescription)?->metadata?->subgroupDescription); ?>
            <?php if($subgroupDescription): ?>
                <p>
                    <?php echo Parsedown::instance()->text($subgroupDescription); ?>

                </p>
            <?php endif; ?>
        <?php endif; ?>
        <?php $__currentLoopData = $subgroup; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $endpoint): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php echo $__env->make("scribe::themes.default.endpoint", array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

<?php /**PATH C:\Users\S7so1\Desktop\lab\backend\resources\views/vendor/scribe/themes/default/groups.blade.php ENDPATH**/ ?>
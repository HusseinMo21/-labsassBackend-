<?php
    $html ??= []; $class = $html['class'] ?? null;
?>
<b style="line-height: 2;"><code><?php echo e($name); ?></code></b>&nbsp;&nbsp;
<?php if($type): ?><small><?php echo e($type); ?></small><?php endif; ?>&nbsp;
<?php if($isInput && !$required): ?><i>optional</i><?php endif; ?> &nbsp;
<?php if($isInput && empty($hasChildren)): ?>
    <?php
        $isList = Str::endsWith($type, '[]');
        $fullName = str_replace('[]', '.0', $fullName ?? $name);
        $baseType = $isList ? substr($type, 0, -2) : $type;
        // Ignore the first '[]': the frontend will take care of it
        while (\Str::endsWith($baseType, '[]')) {
            $fullName .= '.0';
            $baseType = substr($baseType, 0, -2);
        }
        // When the body is an array, the item names will be ".0.thing"
        $fullName = ltrim($fullName, '.');
        $inputType = match($baseType) {
            'number', 'integer' => 'number',
            'file' => 'file',
            default => 'text',
        };
    ?>
    <?php if($type === 'boolean'): ?>
        <label data-endpoint="<?php echo e($endpointId); ?>" style="display: none">
            <input type="radio" name="<?php echo e($fullName); ?>"
                   value="<?php echo e($component === 'body' ? 'true' : 1); ?>"
                   data-endpoint="<?php echo e($endpointId); ?>"
                   data-component="<?php echo e($component); ?>" <?php if($class): ?>class="<?php echo e($class); ?>"<?php endif; ?>
            >
            <code>true</code>
        </label>
        <label data-endpoint="<?php echo e($endpointId); ?>" style="display: none">
            <input type="radio" name="<?php echo e($fullName); ?>"
                   value="<?php echo e($component === 'body' ? 'false' : 0); ?>"
                   data-endpoint="<?php echo e($endpointId); ?>"
                   data-component="<?php echo e($component); ?>" <?php if($class): ?>class="<?php echo e($class); ?>"<?php endif; ?>
            >
            <code>false</code>
        </label>
    <?php elseif($isList): ?>
        <input type="<?php echo e($inputType); ?>" style="display: none"
               <?php if($inputType === 'number'): ?>step="any"<?php endif; ?>
               name="<?php echo e($fullName."[0]"); ?>" <?php if($class): ?>class="<?php echo e($class); ?>"<?php endif; ?>
               data-endpoint="<?php echo e($endpointId); ?>"
               data-component="<?php echo e($component); ?>">
        <input type="<?php echo e($inputType); ?>" style="display: none"
               name="<?php echo e($fullName."[1]"); ?>" <?php if($class): ?>class="<?php echo e($class); ?>"<?php endif; ?>
               data-endpoint="<?php echo e($endpointId); ?>"
               data-component="<?php echo e($component); ?>">
    <?php else: ?>
        <input type="<?php echo e($inputType); ?>" style="display: none"
               <?php if($inputType === 'number'): ?>step="any"<?php endif; ?>
               name="<?php echo e($fullName); ?>" <?php if($class): ?>class="<?php echo e($class); ?>"<?php endif; ?>
               data-endpoint="<?php echo e($endpointId); ?>"
               value="<?php echo (isset($example) && (is_string($example) || is_numeric($example))) ? $example : ''; ?>"
               data-component="<?php echo e($component); ?>">
    <?php endif; ?>
<?php endif; ?>
<br>
<?php
    if($example !== null && $example !== '' && !is_array($example)) {
        $exampleAsString = $example;
        if (is_bool($example)) {
            $exampleAsString = $example ? "true" : "false";
        }
        $description .= " Example: `$exampleAsString`";
    }
?>
<?php echo Parsedown::instance()->text(trim($description)); ?>

<?php if(!empty($enumValues)): ?>
Must be one of:
<ul style="list-style-type: square;"><?php echo implode(" ", array_map(fn($val) => "<li><code>$val</code></li>", $enumValues)); ?></ul>
<?php endif; ?>
<?php /**PATH C:\Users\S7so1\Desktop\lab\backend\resources\views/vendor/scribe/components/field-details.blade.php ENDPATH**/ ?>
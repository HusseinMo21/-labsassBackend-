<?php
    use Knuckles\Scribe\Tools\Utils as u;
    /** @var  Knuckles\Camel\Output\OutputEndpointData $endpoint */
?>

<h2 id="<?php echo $endpoint->fullSlug(); ?>"><?php echo e($endpoint->name()); ?></h2>

<p>
<?php $__env->startComponent('scribe::components.badges.auth', ['authenticated' => $endpoint->isAuthed()]); ?>
<?php echo $__env->renderComponent(); ?>
</p>

<?php echo Parsedown::instance()->text($endpoint->metadata->description ?: ''); ?>


<span id="example-requests-<?php echo $endpoint->endpointId(); ?>">
<blockquote><?php echo e(u::trans("scribe::endpoint.example_request")); ?>:</blockquote>

<?php $__currentLoopData = $metadata['example_languages']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $language): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>

<div class="<?php echo e($language); ?>-example">
    <?php echo $__env->make("scribe::partials.example-requests.$language", array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</div>

<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</span>

<span id="example-responses-<?php echo $endpoint->endpointId(); ?>">
<?php if($endpoint->isGet() || $endpoint->hasResponses()): ?>
    <?php $__currentLoopData = $endpoint->responses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $response): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <blockquote>
            <p><?php echo e(u::trans("scribe::endpoint.example_response")); ?> (<?php echo e($response->fullDescription()); ?>):</p>
        </blockquote>
        <?php if(count($response->headers)): ?>
        <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http"><?php $__currentLoopData = $response->headers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $header => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<?php echo e($header); ?>: <?php echo e(is_array($value) ? implode('; ', $value) : $value); ?>

<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?> </code></pre></details> <?php endif; ?>
        <pre>
<?php if(is_string($response->content) && Str::startsWith($response->content, "<<binary>>")): ?>
<code><?php echo u::trans("scribe::endpoint.responses.binary"); ?> - <?php echo e(htmlentities(str_replace("<<binary>>", "", $response->content))); ?></code>
<?php elseif($response->status == 204): ?>
<code><?php echo u::trans("scribe::endpoint.responses.empty"); ?></code>
<?php else: ?>
<?php ($parsed = json_decode($response->content)); ?>

<code class="language-json" style="max-height: 300px;"><?php echo htmlentities($parsed != null ? json_encode($parsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $response->content); ?></code>
<?php endif; ?> </pre>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
<?php endif; ?>
</span>
<span id="execution-results-<?php echo e($endpoint->endpointId()); ?>" hidden>
    <blockquote><?php echo e(u::trans("scribe::try_it_out.received_response")); ?><span
                id="execution-response-status-<?php echo e($endpoint->endpointId()); ?>"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-<?php echo e($endpoint->endpointId()); ?>"
      data-empty-response-text="<<?php echo e(u::trans("scribe::endpoint.responses.empty")); ?>>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-<?php echo e($endpoint->endpointId()); ?>" hidden>
    <blockquote><?php echo e(u::trans("scribe::try_it_out.request_failed")); ?>:</blockquote>
    <pre><code id="execution-error-message-<?php echo e($endpoint->endpointId()); ?>"><?php echo e("\n\n".u::trans("scribe::try_it_out.error_help")); ?></code></pre>
</span>
<form id="form-<?php echo e($endpoint->endpointId()); ?>" data-method="<?php echo e($endpoint->httpMethods[0]); ?>"
      data-path="<?php echo e($endpoint->uri); ?>"
      data-authed="<?php echo e($endpoint->isAuthed() ? 1 : 0); ?>"
      data-hasfiles="<?php echo e($endpoint->hasFiles() ? 1 : 0); ?>"
      data-isarraybody="<?php echo e($endpoint->isArrayBody() ? 1 : 0); ?>"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('<?php echo e($endpoint->endpointId()); ?>', this);">
    <h3>
        <?php echo e(u::trans("scribe::endpoint.request")); ?>&nbsp;&nbsp;&nbsp;
        <?php if($metadata['try_it_out']['enabled'] ?? false): ?>
            <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-<?php echo e($endpoint->endpointId()); ?>"
                    onclick="tryItOut('<?php echo e($endpoint->endpointId()); ?>');"><?php echo e(u::trans("scribe::try_it_out.open")); ?>

            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-<?php echo e($endpoint->endpointId()); ?>"
                    onclick="cancelTryOut('<?php echo e($endpoint->endpointId()); ?>');" hidden><?php echo e(u::trans("scribe::try_it_out.cancel")); ?>

            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-<?php echo e($endpoint->endpointId()); ?>"
                    data-initial-text="<?php echo e(u::trans("scribe::try_it_out.send")); ?>"
                    data-loading-text="<?php echo e(u::trans("scribe::try_it_out.loading")); ?>"
                    hidden><?php echo e(u::trans("scribe::try_it_out.send")); ?>

            </button>
        <?php endif; ?>
    </h3>
    <?php $__currentLoopData = $endpoint->httpMethods; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $method): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <p>
            <?php $__env->startComponent('scribe::components.badges.http-method', ['method' => $method]); ?><?php echo $__env->renderComponent(); ?>
            <b><code><?php echo e($endpoint->uri); ?></code></b>
        </p>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <?php if(count($endpoint->headers)): ?>
        <h4 class="fancy-heading-panel"><b><?php echo e(u::trans("scribe::endpoint.headers")); ?></b></h4>
        <?php $__currentLoopData = $endpoint->headers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $name => $example): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
                $htmlOptions = [];
                if ($endpoint->isAuthed() && $metadata['auth']['location'] == 'header' && $metadata['auth']['name'] == $name) {
                  $htmlOptions = [ 'class' => 'auth-value', ];
                  }
            ?>
            <div style="padding-left: 28px; clear: unset;">
                <?php $__env->startComponent('scribe::components.field-details', [
                  'name' => $name,
                  'type' => null,
                  'required' => true,
                  'description' => null,
                  'example' => $example,
                  'endpointId' => $endpoint->endpointId(),
                  'component' => 'header',
                  'isInput' => true,
                  'html' => $htmlOptions,
                ]); ?>
                <?php echo $__env->renderComponent(); ?>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <?php endif; ?>
    <?php if(count($endpoint->urlParameters)): ?>
        <h4 class="fancy-heading-panel"><b><?php echo e(u::trans("scribe::endpoint.url_parameters")); ?></b></h4>
        <?php $__currentLoopData = $endpoint->urlParameters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $attribute => $parameter): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div style="padding-left: 28px; clear: unset;">
                <?php $__env->startComponent('scribe::components.field-details', [
                  'name' => $parameter->name,
                  'type' => $parameter->type ?? 'string',
                  'required' => $parameter->required,
                  'description' => $parameter->description,
                  'example' => $parameter->example ?? '',
                  'enumValues' => $parameter->enumValues,
                  'endpointId' => $endpoint->endpointId(),
                  'component' => 'url',
                  'isInput' => true,
                ]); ?>
                <?php echo $__env->renderComponent(); ?>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <?php endif; ?>
    <?php if(count($endpoint->queryParameters)): ?>
        <h4 class="fancy-heading-panel"><b><?php echo e(u::trans("scribe::endpoint.query_parameters")); ?></b></h4>
        <?php $__currentLoopData = $endpoint->queryParameters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $attribute => $parameter): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                $htmlOptions = [];
                if ($endpoint->isAuthed() && $metadata['auth']['location'] == 'query' && $metadata['auth']['name'] == $attribute) {
                    $htmlOptions = [ 'class' => 'auth-value', ];
                }
                ?>
            <div style="padding-left: 28px; clear: unset;">
                <?php $__env->startComponent('scribe::components.field-details', [
                  'name' => $parameter->name,
                  'type' => $parameter->type,
                  'required' => $parameter->required,
                  'description' => $parameter->description,
                  'example' => $parameter->example ?? '',
                  'enumValues' => $parameter->enumValues,
                  'endpointId' => $endpoint->endpointId(),
                  'component' => 'query',
                  'isInput' => true,
                  'html' => $htmlOptions,
                ]); ?>
                <?php echo $__env->renderComponent(); ?>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <?php endif; ?>
    <?php if(count($endpoint->nestedBodyParameters)): ?>
        <h4 class="fancy-heading-panel"><b><?php echo e(u::trans("scribe::endpoint.body_parameters")); ?></b></h4>
        <?php if (isset($component)) { $__componentOriginale161855c22de108490dabd205abbc261 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale161855c22de108490dabd205abbc261 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'scribe::components.nested-fields','data' => ['fields' => $endpoint->nestedBodyParameters,'endpointId' => $endpoint->endpointId()]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('scribe::nested-fields'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['fields' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($endpoint->nestedBodyParameters),'endpointId' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($endpoint->endpointId())]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale161855c22de108490dabd205abbc261)): ?>
<?php $attributes = $__attributesOriginale161855c22de108490dabd205abbc261; ?>
<?php unset($__attributesOriginale161855c22de108490dabd205abbc261); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale161855c22de108490dabd205abbc261)): ?>
<?php $component = $__componentOriginale161855c22de108490dabd205abbc261; ?>
<?php unset($__componentOriginale161855c22de108490dabd205abbc261); ?>
<?php endif; ?>
    <?php endif; ?>
</form>

<?php if(count($endpoint->responseFields)): ?>
    <h3><?php echo e(u::trans("scribe::endpoint.response")); ?></h3>
    <h4 class="fancy-heading-panel"><b><?php echo e(u::trans("scribe::endpoint.response_fields")); ?></b></h4>
    <?php if (isset($component)) { $__componentOriginale161855c22de108490dabd205abbc261 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale161855c22de108490dabd205abbc261 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'scribe::components.nested-fields','data' => ['fields' => $endpoint->nestedResponseFields,'endpointId' => $endpoint->endpointId(),'isInput' => false]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('scribe::nested-fields'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['fields' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($endpoint->nestedResponseFields),'endpointId' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($endpoint->endpointId()),'isInput' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(false)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale161855c22de108490dabd205abbc261)): ?>
<?php $attributes = $__attributesOriginale161855c22de108490dabd205abbc261; ?>
<?php unset($__attributesOriginale161855c22de108490dabd205abbc261); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale161855c22de108490dabd205abbc261)): ?>
<?php $component = $__componentOriginale161855c22de108490dabd205abbc261; ?>
<?php unset($__componentOriginale161855c22de108490dabd205abbc261); ?>
<?php endif; ?>
<?php endif; ?>
<?php /**PATH C:\Users\S7so1\Desktop\lab\backend\resources\views/vendor/scribe/themes/default/endpoint.blade.php ENDPATH**/ ?>
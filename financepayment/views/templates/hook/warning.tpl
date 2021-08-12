{if $display}
<div class="card cart-error" style="background-color:#ffebee; color: white; margin-top: 2rem">
    <div class="card-container">
        <div class="card-block">
            <h1 class="h1">{$title}</h1>
        </div>
        <hr class="seperator">
        <div class="card-block">
            <p>{$message}</p>
        </div>
    </div>
</div>
{/if}
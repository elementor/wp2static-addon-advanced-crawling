<h2>Advanced Crawling Options</h2>

<form
    name="wp2static-advanced-crawling-save-options"
    method="POST"
    action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">

    <?php wp_nonce_field( $view['nonce_action'] ); ?>
    <input name="action" type="hidden" value="wp2static_advanced_crawling_save_options" />

<table class="widefat striped">
    <tbody>

        <tr>
            <td style="width:50%;">
                <label
                    for="<?php echo $view['options']['crawlChunkSize']->name; ?>"
                ><?php echo $view['options']['crawlChunkSize']->label; ?></label>
            </td>
            <td>
                <input
                    id="<?php echo $view['options']['crawlChunkSize']->name; ?>"
                    name="<?php echo $view['options']['crawlChunkSize']->name; ?>"
                    type="text"
                    value="<?php echo $view['options']['crawlChunkSize']->value !== '' ? $view['options']['crawlChunkSize']->value : '20'; ?>"
                />
            </td>
        </tr>

    </tbody>
</table>

<br>

<button class="button btn-primary">Save Options</button>

</form>


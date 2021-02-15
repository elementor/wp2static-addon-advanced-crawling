<h2>Advanced Crawling Options</h2>

<form
    name="wp2static-advanced-crawling-save-options"
    method="POST"
    action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">

    <?php wp_nonce_field( $view['nonce_action'] ); ?>
    <input name="action" type="hidden" value="wp2static_advanced_crawling_save_options" />

<div class="wrap">
<table class="widefat striped">
    <tbody>

        <tr>
            <td>
                <label
                    for="<?php echo $view['options']['additionalHostsToRewrite']->name; ?>"
                ><?php echo $view['options']['additionalHostsToRewrite']->label; ?></label>
            </td>
            <td>
                <textarea
                    id="<?php echo $view['options']['additionalHostsToRewrite']->name; ?>"
                    name="<?php echo $view['options']['additionalHostsToRewrite']->name; ?>"
                    cols=30
                    rows=10
                ><?php echo $view['options']['additionalHostsToRewrite']->blob_value; ?></textarea>
            </td>
        </tr>

        <tr>
            <td>
                <label
                    for="<?php echo $view['options']['additionalPathsToCrawl']->name; ?>"
                ><?php echo $view['options']['additionalPathsToCrawl']->label; ?></label>
            </td>
            <td>
                <textarea
                    id="<?php echo $view['options']['additionalPathsToCrawl']->name; ?>"
                    name="<?php echo $view['options']['additionalPathsToCrawl']->name; ?>"
                    cols=30
                    rows=10
                ><?php echo $view['options']['additionalPathsToCrawl']->blob_value; ?></textarea>
            </td>
        </tr>

        <tr>
            <td>
                <label
                    for="<?php echo $view['options']['addURLsWhileCrawling']->name; ?>"
                ><?php echo $view['options']['addURLsWhileCrawling']->label; ?></label>
            </td>
            <td>
                <input
                    id="<?php echo $view['options']['addURLsWhileCrawling']->name; ?>"
                    name="<?php echo $view['options']['addURLsWhileCrawling']->name; ?>"
                    value="1"
                    type="checkbox"
                    <?php echo (int) $view['options']['addURLsWhileCrawling']->value === 1 ? 'checked' : ''; ?>
                />
            </td>
        </tr>

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

        <tr>
            <td>
                <label
                    for="<?php echo $view['options']['crawlOnlyChangedURLs']->name; ?>"
                ><?php echo $view['options']['crawlOnlyChangedURLs']->label; ?></label>
            </td>
            <td>
                <input
                    id="<?php echo $view['options']['crawlOnlyChangedURLs']->name; ?>"
                    name="<?php echo $view['options']['crawlOnlyChangedURLs']->name; ?>"
                    value="1"
                    type="checkbox"
                    <?php echo (int) $view['options']['crawlOnlyChangedURLs']->value === 1 ? 'checked' : ''; ?>
                />
            </td>
        </tr>

        <tr>
            <td style="width:50%;">
                <label
                    for="<?php echo $view['options']['crawlProgressReportInterval']->name; ?>"
                ><?php echo $view['options']['crawlProgressReportInterval']->label; ?></label>
            </td>
            <td>
                <input
                    id="<?php echo $view['options']['crawlProgressReportInterval']->name; ?>"
                    name="<?php echo $view['options']['crawlProgressReportInterval']->name; ?>"
                    type="text"
                    value="<?php echo $view['options']['crawlProgressReportInterval']->value !== '' ? $view['options']['crawlProgressReportInterval']->value : '20'; ?>"
                />
            </td>
        </tr>

        <tr>
            <td>
                <label
                    for="<?php echo $view['options']['crawlSitemaps']->name; ?>"
                ><?php echo $view['options']['crawlSitemaps']->label; ?></label>
            </td>
            <td>
                <input
                    id="<?php echo $view['options']['crawlSitemaps']->name; ?>"
                    name="<?php echo $view['options']['crawlSitemaps']->name; ?>"
                    value="1"
                    type="checkbox"
                    <?php echo (int) $view['options']['crawlSitemaps']->value === 1 ? 'checked' : ''; ?>
                />
            </td>
        </tr>

        <tr>
            <td>
                <label
                    for="<?php echo $view['options']['detectRedirectionPluginURLs']->name; ?>"
                ><?php echo $view['options']['detectRedirectionPluginURLs']->label; ?></label>
            </td>
            <td>
                <input
                    id="<?php echo $view['options']['detectRedirectionPluginURLs']->name; ?>"
                    name="<?php echo $view['options']['detectRedirectionPluginURLs']->name; ?>"
                    value="1"
                    type="checkbox"
                    <?php echo (int) $view['options']['detectRedirectionPluginURLs']->value === 1 ? 'checked' : ''; ?>
                />
            </td>
        </tr>

        <tr>
            <td>
                <label
                    for="<?php echo $view['options']['filenamesToIgnore']->name; ?>"
                ><?php echo $view['options']['filenamesToIgnore']->label; ?></label>
            </td>
            <td>
                <textarea
                    id="<?php echo $view['options']['filenamesToIgnore']->name; ?>"
                    name="<?php echo $view['options']['filenamesToIgnore']->name; ?>"
                    cols=30
                    rows=10
                ><?php echo $view['options']['filenamesToIgnore']->blob_value; ?></textarea>
            </td>
        </tr>

        <tr>
            <td>
                <label
                    for="<?php echo $view['options']['fileExtensionsToIgnore']->name; ?>"
                ><?php echo $view['options']['fileExtensionsToIgnore']->label; ?></label>
            </td>
            <td>
                <textarea
                    id="<?php echo $view['options']['fileExtensionsToIgnore']->name; ?>"
                    name="<?php echo $view['options']['fileExtensionsToIgnore']->name; ?>"
                    cols=30
                    rows=10
                ><?php echo $view['options']['fileExtensionsToIgnore']->blob_value; ?></textarea>
            </td>
        </tr>

    </tbody>
</table>
</div>

<br>

<button class="button btn-primary">Save Options</button>

</form>


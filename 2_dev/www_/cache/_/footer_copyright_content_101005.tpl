<header:css>
[include_css]
</header:css>
<?php if(trim($section->parametrs->param1)!='d'): ?>
<div class="<?php if(trim($section->parametrs->param1)=='n'): ?>container<?php else: ?>container-fluid<?php endif; ?>">
<?php endif; ?>
<div class="footer-container row">
    <div class="b_social_media col-sm-3 col-xs-6 clearfix">
    <?php foreach($__data->limitObjects($section, $section->objectcount) as $record): ?>

        <a class="b_social_media-link" href="<?php echo $record->field ?>" data-sm-id="<?php echo $record->title ?>" style="background-image: url('<?php echo $record->image ?>');"></a>
    
<?php endforeach; ?>
    </div>
    <div class="b_footer_info col-sm-6 col-xs-12 clearfix">
        <div class="b_copyright" data-seedit="copyright"><span style="font-weight:400;">©&nbsp;</span><?php echo $__data->prj->vars->copyright ?></div>
        <div class="b_footer_text"><?php echo $section->title ?></div>
    </div>
    <div class="b_payment_system col-sm-3 col-xs-6 clearfix">
     <?php echo $section->text ?>
    </div>
</div>
<?php if(trim($section->parametrs->param1)!='d'): ?>
</div>
<?php endif; ?>
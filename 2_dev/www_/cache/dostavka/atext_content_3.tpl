<footer:js>
<?php if(trim($section->parametrs->param5)=="Y"): ?>
[js:jquery/jquery.min.js]
<script type="text/javascript" src="http://yandex.st/share/share.js" charset="utf-8"></script>
[include_js({p6: '<?php echo $section->parametrs->param6 ?>'})]
<?php endif; ?>
</footer:js>
<?php if(trim($section->parametrs->param8)!='d'): ?><div class="<?php if(trim($section->parametrs->param8)=='n'): ?>container<?php else: ?>container-fluid<?php endif; ?>"><?php endif; ?>
<article class="content cont-text part<?php echo $section->id ?>">
    <?php if(!empty($section->title)): ?>
    <header>
        <<?php echo $section->title_tag ?> class="content-title">
            <span data-content="title"><?php echo $section->title ?></span> 
        </<?php echo $section->title_tag ?>>
    </header> 
    <?php endif; ?>
    <?php if(!empty($section->image)): ?>
    <div class="content-image" data-content="image" >
        <img src="<?php echo $section->image ?>" alt="<?php echo $section->image_alt ?>" title="<?php echo $section->image_title ?>">
    </div>
    <?php endif; ?>
    <?php if(!empty($section->text)): ?>
        <div class="content-text" data-content="text"><?php echo $section->text ?></div> 
    <?php endif; ?>
<?php $__data->recordsWrapperStart($section->id) ?>
    <nav class="class-navigator top">
        <?php echo SE_PARTSELECTOR($section->id,count($section->objects),
               $section->objectcount, getRequest("item",1), getRequest("sel",1)) ?>
    </nav>
<?php foreach($__data->limitObjects($section, $section->objectcount) as $record): ?>

    <section class="object record-item obj<?php echo $record->id ?>" <?php echo $__data->editItemRecord($section->id, $record->id) ?>>
        <?php if(!empty($record->title)): ?>
        <header>
            <<?php echo $record->title_tag ?> class="object-title">
                <span data-record="title"><?php echo $record->title ?></span> 
            </<?php echo $record->title_tag ?>>
        </header> 
        <?php endif; ?>
        <?php if(!empty($record->image)): ?>
            <div class="object-image" data-record="image">
                <img class="object-img" border="0" src="<?php echo $record->image_prev ?>" border="0" alt="<?php echo $record->image_alt ?>">
            </div>
        <?php endif; ?>
        <?php if(!empty($record->note)): ?>
            <div class="object-note" data-record="note"><?php echo $record->note ?></div> 
        <?php endif; ?>
        <?php if(!empty($record->text)): ?>
            <a class="link-next" href="<?php echo $record->link_detail ?>#show<?php echo $section->id ?>_<?php echo $record->id ?>"><?php echo $section->parametrs->param1 ?></a> 
        <?php endif; ?>
    </section> 

<?php endforeach; ?>   
    <nav class="class-navigator bottom">
        <?php echo SE_PARTSELECTOR($section->id,count($section->objects),
               $section->objectcount, getRequest("item",1), getRequest("sel",1)) ?>
    </nav>
<?php $__data->recordsWrapperEnd() ?>
</article>
<?php if(trim($section->parametrs->param8)!='d'): ?></div><?php endif; ?>
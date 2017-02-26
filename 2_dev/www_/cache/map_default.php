<body>
  <div data-wrap="global0" id="global0"><?php echo se_getContainer(100) ?>
  </div>
  <div data-wrap="content0" id="content"><?php echo se_getContainer(0) ?>
  </div>
  <div data-wrap="content1" id="content1"><?php echo se_getContainer(1) ?>
  </div>
  <div data-wrap="global1" id="global1"><?php echo se_getContainer(101) ?>
  </div>
<?php if (!empty(seData::getInstance()->footer)) echo "\n".replace_link(join("\n", seData::getInstance()->footer)) ?></body>
<?php

/**
 * Active search widget that work with tables of records generated by Cgridview
 * @uses Twitter Bootrap version 2.1.0
 * @author Fred <mconyango@gmail.com>
 */
class AjaxSearch extends CWidget
{

    /**
     * The grid id to be searched
     * @var type
     */
    public $gridID;

    /**
     * Form ID
     * @var type
     */
    public $formID;

    /**
     * Model object
     * This is required
     * @var type
     */
    public $model;

    /**
     * form action
     * @var type
     */
    public $action = NULL;

    /**
     * form method
     * @var type
     */
    public $method = 'get';

    /**
     * Base url to the published assets for this extension
     * @var type
     */
    private $my_assets_base_url;

    /**
     * Form htmlOptions
     * @var type
     */
    public $formHtmlOptions = [
        'class' => 'form-search',
    ];

    /**
     * The attribute name of the search text field
     * @var type
     */
    public $search_field_name = null;

    /**
     * Text field Html options
     * @var type
     */
    public $searchFieldHtmlOptions = [
        'class' => 'form-control',
        'placeholder' => 'Search ....',
    ];

    /**
     * Whether to show label against the search field
     * @var type
     */
    public $show_label = false;

    /**
     *
     * @var type
     */
    public $search_field_template = '<div class="input-group">{{search_field}}<span class="input-group-addon"><i class="fa fa-search"></i></span></div>';

    /**
     * Type of grid to be search. either cgridview or clistview
     * @var type
     */
    public $grid_type = 'cgridview';

    /**
     * What triggers search (keypress,blur)
     * if is is set null or any other values apart from keypress or blur then the default behavior is executed (enter key or submit button)
     * @var type
     */
    public $search_trigger = 'keypress';

    public function init()
    {
        //initialize the form
        if (isset($this->formHtmlOptions['action']))
            $this->action = $this->formHtmlOptions['action'];
        if (empty($this->action))
            $this->action = Yii::app()->createUrl($this->getOwner()->route, $this->getOwner()->actionParams);

        if (isset($this->formHtmlOptions['method']))
            $this->method = $this->formHtmlOptions['method'];

        if (empty($this->formID) && isset($this->formHtmlOptions['id']))
            $this->formID = $this->formHtmlOptions['id'];
        if (empty($this->formID))
            $this->formID = $this->gridID . '-active-search-form';
        $this->formHtmlOptions['id'] = $this->formID;

        if (empty($this->search_field_name))
            $this->search_field_name = ActiveRecord::SEARCH_FIELD;
        if (isset($this->searchFieldHtmlOptions['name']))
            $this->search_field_name = $this->searchFieldHtmlOptions['name'];
        $this->registerScripts();

        parent::init();
    }

    public function run()
    {
        echo CHtml::beginForm($this->action, $this->method, $this->formHtmlOptions);
        $search_field = Common::myStringReplace($this->search_field_template, [
                    '{{label_text}}' => Lang::t('Search'),
                    '{{search_field}}' => CHtml::activeTextField($this->model, $this->search_field_name, $this->searchFieldHtmlOptions),
        ]);
        echo $search_field;
        echo CHtml::endForm();
    }

    protected function registerScripts()
    {
        //register scripts
        $assets = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'assets';
        $this->my_assets_base_url = Yii::app()->assetManager->publish($assets);

        $options = CJavaScript::encode([
                    'form_id' => $this->formID,
                    'grid_id' => $this->gridID,
                    'grid_type' => $this->grid_type,
                    'search_trigger' => $this->search_trigger,
        ]);
        $cs = Yii::app()->clientScript;
        $cs->registerScriptFile($this->my_assets_base_url . '/ajax-search.js', CClientScript::POS_END)
                ->registerScript('active_search_' . $this->formID, "MyApp.plugin.ajaxSearch(" . $options . ");");
    }

}

?>
<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2014 Daniel Garner
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version. 
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */ 
class ticker extends Module
{
    public function __construct(database $db, user $user, $mediaid = '', $layoutid = '', $regionid = '', $lkid = '')
    {
        // Must set the type of the class
        $this->type = 'ticker';
    
        // Must call the parent class   
        parent::__construct($db, $user, $mediaid, $layoutid, $regionid, $lkid);
    }

    private function InstallFiles() {
        $media = new Media();
        $media->AddModuleFile('modules/preview/vendor/jquery-1.11.1.min.js');
        $media->AddModuleFile('modules/preview/vendor/moment.js');
        $media->AddModuleFile('modules/preview/vendor/jquery.marquee.min.js');
        $media->AddModuleFile('modules/preview/vendor/jquery-cycle-2.1.6.min.js');
        $media->AddModuleFile('modules/preview/xibo-layout-scaler.js');
        $media->AddModuleFile('modules/preview/xibo-text-render.js');
    }
    
    /**
     * Return the Add Form as HTML
     * @return 
     */
    public function AddForm()
    {
        $db         =& $this->db;
        $user       =& $this->user;
                
        // Would like to get the regions width / height 
        $layoutid   = $this->layoutid;
        $regionid   = $this->regionid;
        $rWidth     = Kit::GetParam('rWidth', _REQUEST, _STRING);
        $rHeight    = Kit::GetParam('rHeight', _REQUEST, _STRING);

        Theme::Set('form_id', 'ModuleForm');
        Theme::Set('form_action', 'index.php?p=module&mod=' . $this->type . '&q=Exec&method=AddMedia');
        Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $layoutid . '"><input type="hidden" id="iRegionId" name="regionid" value="' . $regionid . '"><input type="hidden" name="showRegionOptions" value="' . $this->showRegionOptions . '" />');
    
        $formFields = array();
        $formFields[] = FormManager::AddCombo(
                    'sourceid', 
                    __('Source Type'), 
                    NULL,
                    array(array('sourceid' => '1', 'source' => __('Feed')), array('sourceid' => '2', 'source' => __('DataSet'))),
                    'sourceid',
                    'source',
                    __('The source for this Ticker'), 
                    's');

        $formFields[] = FormManager::AddText('uri', __('Feed URL'), NULL, 
            __('The Link for the RSS feed'), 'f', '', 'feed-fields');

        $datasets = $user->DataSetList();
        array_unshift($datasets, array('datasetid' => '0', 'dataset' => 'None'));
        Theme::Set('dataset_field_list', $datasets);

        $formFields[] = FormManager::AddCombo(
                    'datasetid', 
                    __('DataSet'), 
                    NULL,
                    $datasets,
                    'datasetid',
                    'dataset',
                    __('Please select the DataSet to use as a source of data for this ticker.'), 
                    'd', 'dataset-fields');

        $formFields[] = FormManager::AddNumber('duration', __('Duration'), NULL, 
            __('The duration in seconds this counter should be displayed'), 'd', 'required');

        Theme::Set('form_fields', $formFields);

        // Field dependencies
        $sourceFieldDepencies_1 = array(
                '.feed-fields' => array('display' => 'block'),
                '.dataset-fields' => array('display' => 'none'),
            );

        $sourceFieldDepencies_2 = array(
                '.feed-fields' => array('display' => 'none'),
                '.dataset-fields' => array('display' => 'block'),
            );

        $this->response->AddFieldAction('sourceid', 'init', 1, $sourceFieldDepencies_1);
        $this->response->AddFieldAction('sourceid', 'change', 1, $sourceFieldDepencies_1);
        $this->response->AddFieldAction('sourceid', 'init', 2, $sourceFieldDepencies_2);
        $this->response->AddFieldAction('sourceid', 'change', 2, $sourceFieldDepencies_2);
                
        // Return
        $this->response->html = Theme::RenderReturn('form_render');
        $this->response->dialogTitle = __('Add New Ticker');

        if ($this->showRegionOptions)
        {
            $this->response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=timeline&layoutid=' . $layoutid . '&regionid=' . $regionid . '&q=RegionOptions")');
        }
        else
        {
            $this->response->AddButton(__('Cancel'), 'XiboDialogClose()');
        }
        
        $this->response->AddButton(__('Save'), '$("#ModuleForm").submit()');

        return $this->response;
    }
    
    /**
     * Return the Edit Form as HTML
     * @return 
     */
    public function EditForm()
    {
        $db =& $this->db;
        
        $layoutid = $this->layoutid;
        $regionid = $this->regionid;
        $mediaid = $this->mediaid;

        // Permissions
        if (!$this->auth->edit)
        {
            $this->response->SetError('You do not have permission to edit this assignment.');
            $this->response->keepOpen = true;
            return $this->response;
        }

        Theme::Set('form_id', 'ModuleForm');
        Theme::Set('form_action', 'index.php?p=module&mod=' . $this->type . '&q=Exec&method=EditMedia');
        Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $layoutid . '"><input type="hidden" id="iRegionId" name="regionid" value="' . $regionid . '"><input type="hidden" name="showRegionOptions" value="' . $this->showRegionOptions . '" /><input type="hidden" id="mediaid" name="mediaid" value="' . $mediaid . '">');
        
        $formFields = array();

        // What is the source for this ticker?
        $sourceId = $this->GetOption('sourceId');
        $dataSetId = $this->GetOption('datasetid');

        $tabs = array();
        $tabs[] = FormManager::AddTab('general', __('General'));
        $tabs[] = FormManager::AddTab('format', __('Format'));
        $tabs[] = FormManager::AddTab('advanced', __('Advanced'));
        Theme::Set('form_tabs', $tabs);

        $field_name = FormManager::AddText('name', __('Name'), $this->GetOption('name'), 
            __('An optional name for this media'), 'n');

        $field_duration = FormManager::AddNumber('duration', __('Duration'), $this->duration, 
            __('The duration in seconds this item should be displayed'), 'd', 'required', '', ($this->auth->modifyPermissions));

        // Common fields
        $field_direction = FormManager::AddCombo(
                'direction', 
                __('Direction'), 
                $this->GetOption('direction'),
                array(
                    array('directionid' => 'none', 'direction' => __('None')), 
                    array('directionid' => 'left', 'direction' => __('Left')), 
                    array('directionid' => 'right', 'direction' => __('Right')), 
                    array('directionid' => 'up', 'direction' => __('Up')), 
                    array('directionid' => 'down', 'direction' => __('Down')),
                    array('directionid' => 'single', 'direction' => __('Single'))
                ),
                'directionid',
                'direction',
                __('Please select which direction this text should scroll. If scrolling is not required, select None'), 
                's');

        $field_scrollSpeed = FormManager::AddNumber('scrollSpeed', __('Scroll Speed'), $this->GetOption('scrollSpeed'), 
            __('The scroll speed to apply if a direction is specified. Higher is faster.'), 'e');

        $field_itemsPerPage = FormManager::AddNumber('itemsPerPage', __('Items per page'), $this->GetOption('itemsPerPage'), 
            __('When in single mode how many items per page should be shown.'), 'p');

        $field_updateInterval = FormManager::AddNumber('updateInterval', __('Update Interval (mins)'), $this->GetOption('updateInterval', 5), 
            __('Please enter the update interval in minutes. This should be kept as high as possible. For example, if the data will only change once per day this could be set to 60.'),
            'n', 'required');

        $field_durationIsPerItem = FormManager::AddCheckbox('durationIsPerItem', __('Duration is per item'), 
            $this->GetOption('durationIsPerItem'), __('The duration specified is per item otherwise it is per feed.'), 
            'i');

        $field_itemsSideBySide = FormManager::AddCheckbox('itemsSideBySide', __('Show items side by side?'), 
            $this->GetOption('itemsSideBySide'), __('Should items be shown side by side?'), 
            's');

        // Data Set Source
        if ($sourceId == 2) {

            $formFields['general'][] = $field_name;
            $formFields['general'][] = $field_duration;
            $formFields['general'][] = $field_direction;
            $formFields['general'][] = $field_scrollSpeed;
            $formFields['advanced'][] = $field_durationIsPerItem;
            $formFields['advanced'][] = $field_updateInterval;

            // Extra Fields for the DataSet
            $formFields['general'][] = FormManager::AddNumber('ordering', __('Order'), $this->GetOption('ordering'), 
                __('Please enter a SQL clause for how this dataset should be ordered'), 'o');

            $formFields['general'][] = FormManager::AddText('filter', __('Filter'), $this->GetOption('filter'), 
                __('Please enter a SQL clause to filter this DataSet.'), 'f');

            $formFields['advanced'][] = FormManager::AddNumber('lowerLimit', __('Lower Row Limit'), $this->GetOption('lowerLimit'), 
                __('Please enter the Lower Row Limit for this DataSet (enter 0 for no limit)'), 'l');

            $formFields['advanced'][] = FormManager::AddNumber('upperLimit', __('Upper Row Limit'), $this->GetOption('upperLimit'), 
                __('Please enter the Upper Row Limit for this DataSet (enter 0 for no limit)'), 'u');

            $formFields['format'][] = $field_itemsPerPage;
            $formFields['format'][] = $field_itemsSideBySide;

            Theme::Set('columns', $db->GetArray(sprintf("SELECT DataSetColumnID, Heading FROM datasetcolumn WHERE DataSetID = %d ", $dataSetId)));

            $formFields['general'][] = FormManager::AddRaw(Theme::RenderReturn('media_form_ticker_dataset_edit'));
        }
        else {
            // Extra Fields for the Ticker
            $formFields['general'][] = FormManager::AddText('uri', __('Feed URL'), urldecode($this->GetOption('uri')), 
                __('The Link for the RSS feed'), 'f');

            $formFields['general'][] = $field_name;
            $formFields['general'][] = $field_duration;
            $formFields['general'][] = $field_direction;
            $formFields['format'][] = $field_scrollSpeed;
            
            $formFields['format'][] = FormManager::AddNumber('numItems', __('Number of Items'), $this->GetOption('numItems'), 
                __('The Number of RSS items you want to display'), 'o');

            $formFields['format'][] = $field_itemsPerPage;

            $formFields['format'][] = FormManager::AddText('copyright', __('Copyright'), $this->GetOption('copyright'), 
                __('Copyright information to display as the last item in this feed.'), 'f');

            $formFields['advanced'][] = $field_updateInterval;

            $formFields['format'][] = FormManager::AddCombo(
                    'takeItemsFrom', 
                    __('Take items from the '), 
                    $this->GetOption('takeItemsFrom'),
                    array(
                        array('takeitemsfromid' => 'start', 'takeitemsfrom' => __('Start of the Feed')),
                        array('takeitemsfromid' => 'end', 'takeitemsfrom' => __('End of the Feed'))
                    ),
                    'takeitemsfromid',
                    'takeitemsfrom',
                    __('Take the items from the beginning or the end of the list'), 
                    't');

            $formFields['format'][] = $field_durationIsPerItem;
            $formFields['format'][] = $field_itemsSideBySide;

            $formFields['format'][] = FormManager::AddText('dateFormat', __('Date Format'), $this->GetOption('dateFormat'), 
                __('The format to apply to all dates returned by the ticker. In PHP date format: http://uk3.php.net/manual/en/function.date.php'), 'f');

            $subs = array(
                    array('Substitute' => 'Title'),
                    array('Substitute' => 'Description'),
                    array('Substitute' => 'Date'),
                    array('Substitute' => 'Content'),
                    array('Substitute' => 'Copyright'),
                    array('Substitute' => 'Link'),
                    array('Substitute' => 'PermaLink'),
                    array('Substitute' => 'Tag|Namespace')
                );
            Theme::Set('substitutions', $subs);

            $formFields['general'][] = FormManager::AddRaw(Theme::RenderReturn('media_form_ticker_edit'));

            $formFields['advanced'][] = FormManager::AddText('allowedAttributes', __('Allowable Attributes'), $this->GetOption('allowedAttributes'), 
                __('A comma separated list of attributes that should not be stripped from the incoming feed.'), '');

            $formFields['advanced'][] = FormManager::AddText('stripTags', __('Strip Tags'), $this->GetOption('stripTags'), 
                __('A comma separated list of HTML tags that should be stripped from the feed in addition to the default ones.'), '');
        }

        // Get the text out of RAW
        $rawXml = new DOMDocument();
        $rawXml->loadXML($this->GetRaw());
        
        Debug::LogEntry('audit', 'Raw XML returned: ' . $this->GetRaw());
        
        // Get the Text Node out of this
        $textNodes = $rawXml->getElementsByTagName('template');
        $textNode = $textNodes->item(0);
        Theme::Set('text', $textNode->nodeValue);

        $formFields['general'][] = FormManager::AddMultiText('ta_text', NULL, $textNode->nodeValue, 
            __('Enter the template. Please note that the background colour has automatically coloured to your region background colour.'), 't', 10);

        // Get the CSS node
        $cssNodes = $rawXml->getElementsByTagName('css');
        if ($cssNodes->length > 0) {
            $cssNode = $cssNodes->item(0);
        }

        $formFields['advanced'][] = FormManager::AddMultiText('ta_css', NULL, (($cssNodes->length > 0) ? $cssNode->nodeValue : ''), 
            __('Optional Stylesheet'), 's', 10);

        Theme::Set('form_fields_general', $formFields['general']);
        Theme::Set('form_fields_format', $formFields['format']);
        Theme::Set('form_fields_advanced', $formFields['advanced']);

        // Generate the Response
        $this->response->html = Theme::RenderReturn('form_render');
        $this->response->callBack   = 'text_callback';
        $this->response->dialogTitle = __('Edit Ticker');

        if ($this->showRegionOptions)
        {
            $this->response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=timeline&layoutid=' . $layoutid . '&regionid=' . $regionid . '&q=RegionOptions")');
        }
        else
        {
            $this->response->AddButton(__('Cancel'), 'XiboDialogClose()');
        }

        $this->response->AddButton(__('Save'), '$("#ModuleForm").submit()');

        return $this->response;
    }
    
    /**
     * Add Media to the Database
     * @return 
     */
    public function AddMedia()
    {
        $layoutid = $this->layoutid;
        $regionid = $this->regionid;
        $mediaid = $this->mediaid;
        
        // Other properties
        $sourceId = Kit::GetParam('sourceid', _POST, _INT);
        $uri = Kit::GetParam('uri', _POST, _URI);
        $dataSetId = Kit::GetParam('datasetid', _POST, _INT, 0);
        $duration = Kit::GetParam('duration', _POST, _INT, 0);
        $template = '';
        
        // Must have a duration
        if ($duration == 0)
            trigger_error(__('Please enter a duration'), E_USER_ERROR);

        if ($sourceId == 1) {
            // Feed
            
            // Validate the URL
            if ($uri == "" || $uri == "http://")
                trigger_error(__('Please enter a Link for this Ticker'), E_USER_ERROR);

            $template = '<p><span style="font-size:22px;"><span style="color:#FFFFFF;">[Title]</span></span></p>';
        }
        else if ($sourceId == 2) {
            // DataSet
            
            // Validate Data Set Selected
            if ($dataSetId == 0)
                trigger_error(__('Please select a DataSet'), E_USER_ERROR);

            // Check we have permission to use this DataSetId
            if (!$this->user->DataSetAuth($dataSetId))
                trigger_error(__('You do not have permission to use that dataset'), E_USER_ERROR);
        }
        else {
            // Only supported two source types at the moment
            trigger_error(__('Unknown Source Type'));
        }
        
        // Required Attributes
        $this->mediaid  = md5(uniqid());
        $this->duration = $duration;
        
        // Any Options
        $this->SetOption('xmds', true);
        $this->SetOption('sourceId', $sourceId);
        $this->SetOption('uri', $uri);
        $this->SetOption('datasetid', $dataSetId);
        $this->SetOption('updateInterval', 120);
        $this->SetOption('scrollSpeed', 2);

        $this->SetRaw('<template><![CDATA[' . $template . ']]></template><css><![CDATA[]]></css>');
        
        // Should have built the media object entirely by this time
        // This saves the Media Object to the Region
        $this->UpdateRegion();
        
        //Set this as the session information
        setSession('content', 'type', 'ticker');
        
        if ($this->showRegionOptions)
        {
            // We want to load a new form
            $this->response->loadForm = true;
            $this->response->loadFormUri = "index.php?p=module&mod=ticker&q=Exec&method=EditForm&layoutid=$this->layoutid&regionid=$regionid&mediaid=$this->mediaid";
        }
        
        return $this->response;
    }
    
    /**
     * Edit Media in the Database
     * @return 
     */
    public function EditMedia()
    {
        $layoutid   = $this->layoutid;
        $regionid   = $this->regionid;
        $mediaid    = $this->mediaid;

        if (!$this->auth->edit)
        {
            $this->response->SetError('You do not have permission to edit this assignment.');
            $this->response->keepOpen = false;
            return $this->response;
        }

        $sourceId = $this->GetOption('sourceId', 1);
        
        // Other properties
        $uri          = Kit::GetParam('uri', _POST, _URI);
		$name = Kit::GetParam('name', _POST, _STRING);
        $direction    = Kit::GetParam('direction', _POST, _WORD, 'none');
        $text         = Kit::GetParam('ta_text', _POST, _HTMLSTRING);
        $css = Kit::GetParam('ta_css', _POST, _HTMLSTRING);
        $scrollSpeed  = Kit::GetParam('scrollSpeed', _POST, _INT, 2);
        $updateInterval = Kit::GetParam('updateInterval', _POST, _INT, 360);
        $copyright    = Kit::GetParam('copyright', _POST, _STRING);
        $numItems = Kit::GetParam('numItems', _POST, _STRING);
        $takeItemsFrom = Kit::GetParam('takeItemsFrom', _POST, _STRING);
        $durationIsPerItem = Kit::GetParam('durationIsPerItem', _POST, _CHECKBOX);
        $itemsSideBySide = Kit::GetParam('itemsSideBySide', _POST, _CHECKBOX);
        
        // DataSet Specific Options
        $itemsPerPage = Kit::GetParam('itemsPerPage', _POST, _INT);
        $upperLimit = Kit::GetParam('upperLimit', _POST, _INT);
        $lowerLimit = Kit::GetParam('lowerLimit', _POST, _INT);
        $filter = Kit::GetParam('filter', _POST, _STRINGSPECIAL);
        $ordering = Kit::GetParam('ordering', _POST, _STRING);
        
        // If we have permission to change it, then get the value from the form
        if ($this->auth->modifyPermissions)
            $this->duration = Kit::GetParam('duration', _POST, _INT, 0);
        
        // Validation
        if ($text == '')
        {
            $this->response->SetError('Please enter some text');
            $this->response->keepOpen = true;
            return $this->response;
        }

        if ($sourceId == 1) {
            // Feed
            
            // Validate the URL
            if ($uri == "" || $uri == "http://")
                trigger_error(__('Please enter a Link for this Ticker'), E_USER_ERROR);
        }
        else if ($sourceId == 2) {
            // Make sure we havent entered a silly value in the filter
            if (strstr($filter, 'DESC'))
                trigger_error(__('Cannot user ordering criteria in the Filter Clause'), E_USER_ERROR);

            if (!is_numeric($upperLimit) || !is_numeric($lowerLimit))
                trigger_error(__('Limits must be numbers'), E_USER_ERROR);

            if ($upperLimit < 0 || $lowerLimit < 0)
                trigger_error(__('Limits cannot be lower than 0'), E_USER_ERROR);

            // Check the bounds of the limits
            if ($upperLimit < $lowerLimit)
                trigger_error(__('Upper limit must be higher than lower limit'), E_USER_ERROR);
        }
        
        if ($this->duration == 0)
        {
            $this->response->SetError('You must enter a duration.');
            $this->response->keepOpen = true;
            return $this->response;
        }

        if ($numItems != '')
        {
            // Make sure we have a number in here
            if (!is_numeric($numItems))
            {
                $this->response->SetError(__('The value in Number of Items must be numeric.'));
                $this->response->keepOpen = true;
                return $this->response;
            }
        }

        if ($updateInterval < 0)
            trigger_error(__('Update Interval must be greater than or equal to 0'), E_USER_ERROR);
        
        // Any Options
        $this->SetOption('xmds', true);
		$this->SetOption('name', $name);
        $this->SetOption('direction', $direction);
        $this->SetOption('copyright', $copyright);
        $this->SetOption('scrollSpeed', $scrollSpeed);
        $this->SetOption('updateInterval', $updateInterval);
        $this->SetOption('uri', $uri);
        $this->SetOption('numItems', $numItems);
        $this->SetOption('takeItemsFrom', $takeItemsFrom);
        $this->SetOption('durationIsPerItem', $durationIsPerItem);
        $this->SetOption('itemsSideBySide', $itemsSideBySide);
        $this->SetOption('upperLimit', $upperLimit);
        $this->SetOption('lowerLimit', $lowerLimit);
        $this->SetOption('filter', $filter);
        $this->SetOption('ordering', $ordering);
        $this->SetOption('itemsPerPage', $itemsPerPage);
        $this->SetOption('dateFormat', Kit::GetParam('dateFormat', _POST, _STRING));
        $this->SetOption('allowedAttributes', Kit::GetParam('allowedAttributes', _POST, _STRING));
        $this->SetOption('stripTags', Kit::GetParam('stripTags', _POST, _STRING));
        
        // Text Template
        $this->SetRaw('<template><![CDATA[' . $text . ']]></template><css><![CDATA[' . $css . ']]></css>');
        
        // Should have built the media object entirely by this time
        // This saves the Media Object to the Region
        $this->UpdateRegion();
        
        //Set this as the session information
        setSession('content', 'type', 'ticker');
        
        if ($this->showRegionOptions)
        {
            // We want to load a new form
            $this->response->loadForm = true;
            $this->response->loadFormUri = "index.php?p=timeline&layoutid=$layoutid&regionid=$regionid&q=RegionOptions";
        }
        
        return $this->response; 
    }

	public function DeleteMedia() {

		$dataSetId = $this->GetOption('datasetid');

        Kit::ClassLoader('dataset');
        $dataSet = new DataSet($this->db);
        $dataSet->UnlinkLayout($dataSetId, $this->layoutid, $this->regionid, $this->mediaid);

        return parent::DeleteMedia();
    }

	public function GetName() {
		return $this->GetOption('name');
	}

    public function HoverPreview()
    {
        $msgName = __('Name');
        $msgType = __('Type');
        $msgUrl = __('Source');
        $msgDuration = __('Duration');

        $name = $this->GetOption('name');
        $url = urldecode($this->GetOption('uri'));
        $sourceId = $this->GetOption('sourceId', 1);

        // Default Hover window contains a thumbnail, media type and duration
        $output = '<div class="thumbnail"><img alt="' . $this->displayType . ' thumbnail" src="theme/default/img/forms/' . $this->type . '.gif"></div>';
        $output .= '<div class="info">';
        $output .= '    <ul>';
        $output .= '    <li>' . $msgType . ': ' . $this->displayType . '</li>';
        $output .= '    <li>' . $msgName . ': ' . $name . '</li>';

        if ($sourceId == 2)
            $output .= '    <li>' . $msgUrl . ': DataSet</li>';
        else
            $output .= '    <li>' . $msgUrl . ': <a href="' . $url . '" target="_blank" title="' . $msgUrl . '">' . $url . '</a></li>';


        $output .= '    <li>' . $msgDuration . ': ' . $this->duration . ' ' . __('seconds') . '</li>';
        $output .= '    </ul>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Preview
     * @param <type> $width
     * @param <type> $height
     * @return <type>
     */
    public function Preview($width, $height)
    {
        if ($this->previewEnabled == 0)
            return parent::Preview ($width, $height);
        
        return $this->PreviewAsClient($width, $height);
    }

    /**
     * Get Resource
     */
    public function GetResource($displayId = 0)
    {
        // Make sure this module is installed correctly
        $this->InstallFiles();

        // Load in the template
        if ($this->layoutSchemaVersion == 1)
            $template = file_get_contents('modules/preview/Html4TransitionalTemplate.html');
        else
            $template = file_get_contents('modules/preview/HtmlTemplate.html');

        // Replace the View Port Width?
        if (isset($_GET['preview']))
            $template = str_replace('[[ViewPortWidth]]', $this->width, $template);

        // What is the data source for this ticker?
        $sourceId = $this->GetOption('sourceId', 1);

        // Information from the Module
        $direction = $this->GetOption('direction');
        $scrollSpeed = $this->GetOption('scrollSpeed');
        $itemsSideBySide = $this->GetOption('itemsSideBySide', 0);
        $duration = $this->duration;
        $durationIsPerItem = $this->GetOption('durationIsPerItem', 0);
        $numItems = $this->GetOption('numItems', 0);
        $takeItemsFrom = $this->GetOption('takeItemsFrom', 'start');
        $itemsPerPage = $this->GetOption('itemsPerPage', 0);

        // Get the text out of RAW
        $rawXml = new DOMDocument();
        $rawXml->loadXML($this->GetRaw());

        // Get the Text Node
        $textNodes = $rawXml->getElementsByTagName('template');
        $textNode = $textNodes->item(0);
        $text = $textNode->nodeValue;

        // Get the CSS Node
        $cssNodes = $rawXml->getElementsByTagName('css');

        if ($cssNodes->length > 0) {
            $cssNode = $cssNodes->item(0);
            $css = $cssNode->nodeValue;
        }
        else {
            $css = '';
        }

        $options = array(
            'direction' => $direction,
            'duration' => $duration,
            'durationIsPerItem' => (($durationIsPerItem == 0) ? false : true),
            'numItems' => $numItems,
            'takeItemsFrom' => $takeItemsFrom,
            'itemsPerPage' => $itemsPerPage,
            'scrollSpeed' => $scrollSpeed,
            'originalWidth' => $this->width,
            'originalHeight' => $this->height,
            'previewWidth' => Kit::GetParam('width', _GET, _DOUBLE, 0),
            'previewHeight' => Kit::GetParam('height', _GET, _DOUBLE, 0),
            'scaleOverride' => Kit::GetParam('scale_override', _GET, _DOUBLE, 0)
        );

        // Generate a JSON string of substituted items.
        if ($sourceId == 2) {
            $items = $this->GetDataSetItems($displayId, $text);
        }
        else {
            $items = $this->GetRssItems($text);
        }

        // Return empty string if there are no items to show.
        if (count($items) == 0)
            return '';

        // Work out how many pages we will be showing.
        $pages = $numItems;

        if ($numItems > count($items) || $numItems == 0)
            $pages = count($items);

        $pages = ($itemsPerPage > 0) ? ceil($pages / $itemsPerPage) : $pages;
        $totalDuration = ($durationIsPerItem == 0) ? $duration : ($duration * $pages);

        $controlMeta = array('numItems' => $pages, 'totalDuration' => $totalDuration);

        // Replace and Control Meta options
        $template = str_replace('<!--[[[CONTROLMETA]]]-->', '<!-- NUMITEMS=' . $pages . ' -->' . PHP_EOL . '<!-- DURATION=' . $totalDuration . ' -->', $template);

        // Replace the head content
        $headContent  = '';

        if ($itemsSideBySide == 1) {
            $headContent .= '<style type="text/css">';
            $headContent .= ' .item, .page { float: left; }';
            $headContent .= '</style>';
        }

        // Add the CSS if it isn't empty
        if ($css != '') {
            $headContent .= '<style type="text/css">' . $css . '</style>';
        }

        // Add our fonts.css file
        $isPreview = (Kit::GetParam('preview', _REQUEST, _WORD, 'false') == 'true');
        $headContent .= '<link href="' . (($isPreview) ? 'modules/preview/' : '') . 'fonts.css" rel="stylesheet" media="screen">';
        $headContent .= '<style type="text/css">' . file_get_contents(Theme::ItemPath('css/client.css')) . '</style>';

        // Replace the Head Content with our generated javascript
        $template = str_replace('<!--[[[HEADCONTENT]]]-->', $headContent, $template);

        // Add some scripts to the JavaScript Content
        $javaScriptContent  = '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/vendor/' : '') . 'jquery-1.11.1.min.js"></script>';

        // Need the marquee plugin?
        if ($direction != 'none' && $direction != 'single')
            $javaScriptContent .= '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/vendor/' : '') . 'jquery.marquee.min.js"></script>';
        
        // Need the cycle plugin?
        if ($direction == 'single')
            $javaScriptContent .= '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/vendor/' : '') . 'jquery-cycle-2.1.6.min.js"></script>';
        
        $javaScriptContent .= '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/' : '') . 'xibo-layout-scaler.js"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/' : '') . 'xibo-text-render.js"></script>';

        $javaScriptContent .= '<script type="text/javascript">';
        $javaScriptContent .= '   var options = ' . json_encode($options) . ';';
        $javaScriptContent .= '   var items = ' . json_encode($items) . ';';
        $javaScriptContent .= '   $(document).ready(function() { ';
        $javaScriptContent .= '       $("body").xiboLayoutScaler(options); $("#content").xiboTextRender(options, items);';
        $javaScriptContent .= '   }); ';
        $javaScriptContent .= '</script>';

        // Replace the Head Content with our generated javascript
        $template = str_replace('<!--[[[JAVASCRIPTCONTENT]]]-->', $javaScriptContent, $template);

        // Replace the Body Content with our generated text
        $template = str_replace('<!--[[[BODYCONTENT]]]-->', '', $template);

        return $template;
    }

    private function GetRssItems($text) {

        // Make sure we have the cache location configured
        Kit::ClassLoader('file');
        $file = new File($this->db);
        File::EnsureLibraryExists();

        // In the text template replace span with div
        $text = str_replace('span', 'div', $text);

        // Parse the text template
        $matches = '';
        preg_match_all('/\[.*?\]/', $text, $matches);

        Debug::LogEntry('audit', 'Loading SimplePie to handle RSS parsing');
        
        // Use SimplePie to get the feed
        include_once('3rdparty/simplepie/autoloader.php');

        $feed = new SimplePie();
        $feed->set_cache_location($file->GetLibraryCacheUri());
        $feed->set_feed_url(urldecode($this->GetOption('uri')));
        $feed->force_feed(true);
        $feed->set_cache_duration(($this->GetOption('updateInterval', 3600) * 60));
        $feed->handle_content_type();

        // Get a list of allowed attributes
        if ($this->GetOption('allowedAttributes') != '') {
            $attrsStrip = array_diff($feed->strip_attributes, explode(',', $this->GetOption('allowedAttributes')));
            //Debug::Audit(var_export($attrsStrip, true));
            $feed->strip_attributes($attrsStrip);
        }

        // Get a list of tags to strip
        if ($this->GetOption('stripTags') != '') {
            $tagsStrip = array_merge($feed->strip_htmltags, explode(',', $this->GetOption('stripTags')));
            $feed->strip_htmltags($tagsStrip);
        }

        // Init
        $feed->init();

        $dateFormat = $this->GetOption('dateFormat');

        if ($feed->error()) {
            Debug::LogEntry('audit', 'Feed Error: ' . $feed->error());
            return array();
        }

        // Store our formatted items
        $items = array();

        foreach ($feed->get_items() as $item) {

            // Substitute for all matches in the template
            $rowString = $text;
            
            // Substitute
            foreach ($matches[0] as $sub) {
                $replace = '';

                // Pick the appropriate column out
                if (strstr($sub, '|') !== false) {
                    // Use the provided namespace to extract a tag
                    $attribs = NULL;
                    if (substr_count($sub, '|') > 1)
                        list($tag, $namespace, $attribs) = explode('|', $sub);
                    else
                        list($tag, $namespace) = explode('|', $sub);

                    $tags = $item->get_item_tags(str_replace(']', '', $namespace), str_replace('[', '', $tag));
                    Debug::LogEntry('audit', var_export($tags, true));

                    if ($attribs != NULL)
                        $replace = (is_array($tags)) ? $tags[0]['attribs'][''][str_replace(']', '', $attribs)] : '';
                    else
                        $replace = (is_array($tags)) ? $tags[0]['data'] : '';
                }
                else {
                    
                    // Use the pool of standard tags
                    switch ($sub) {
                        case '[Title]':
                            $replace = $item->get_title();
                            break;

                        case '[Description]':
                            $replace = $item->get_description();
                            break;

                        case '[Content]':
                            $replace = $item->get_content();
                            break;

                        case '[Copyright]':
                            $replace = $item->get_copyright();
                            break;

                        case '[Date]':
                            $replace = ($dateFormat == '') ? $item->get_local_date() : $item->get_date($dateFormat);
                            break;

                        case '[PermaLink]':
                            $replace = $item->get_permalink();
                            break;

                        case '[Link]':
                            $replace = $item->get_link();
                            break;
                    }
                }

                // Substitute the replacement we have found (it might be '')
                $rowString = str_replace($sub, $replace, $rowString);
            }

            $items[] = $rowString;
        }

        // Return the formatted items
        return $items;
    }

    private function GetDataSetItems($displayId, $text) {

        $db =& $this->db;

        // Extra fields for data sets
        $dataSetId = $this->GetOption('datasetid');
        $upperLimit = $this->GetOption('upperLimit');
        $lowerLimit = $this->GetOption('lowerLimit');
        $filter = $this->GetOption('filter');
        $ordering = $this->GetOption('ordering');

        Debug::LogEntry('audit', 'Then template for each row is: ' . $text);

        // Combine the column id's with the dataset data
        $matches = '';
        preg_match_all('/\[(.*?)\]/', $text, $matches);

        $columnIds = array();
        
        foreach ($matches[1] as $match) {
            // Get the column id's we are interested in
            Debug::LogEntry('audit', 'Matched column: ' . $match);

            $col = explode('|', $match);
            $columnIds[] = $col[1];
        }

        // Get the dataset results
        Kit::ClassLoader('dataset');
        $dataSet = new DataSet($db);
        $dataSetResults = $dataSet->DataSetResults($dataSetId, implode(',', $columnIds), $filter, $ordering, $lowerLimit, $upperLimit, $displayId, true /* Associative */);

        $items = array();

        foreach ($dataSetResults['Rows'] as $row) {
            // For each row, substitute into our template
            $rowString = $text;

            foreach ($matches[1] as $sub) {
                // Pick the appropriate column out
                $subs = explode('|', $sub);

                $rowString = str_replace('[' . $sub . ']', $row[$subs[0]], $rowString);
            }

            $items[] = $rowString;
        }

        return $items;
    }
    
    public function IsValid() {
        // Can't be sure because the client does the rendering
        return ($this->GetOption('xmds')) ? 1 : 2;
    }
}
?>

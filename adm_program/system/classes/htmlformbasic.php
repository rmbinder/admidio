<?php
/*****************************************************************************
 *
 *  Copyright    : (c) 2004 - 2015 The Admidio Team
 *  Homepage     : http://www.admidio.org
 *  License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

/**
 * @class HtmlFormBasic
 * @brief  Create html form elements
 *
 * This class creates html form elements.
 * Create an instance of an form element and set the input elements inline .
 * The class supports setting all form elements and allows you to configure all attributes programatically.
 * The parsed form object  is returned as string.
 *
 * @par Example of an array with further attributes
 * @code
 * $attrArray = array('class' => 'Classname');
 * @endcode
 * @par Example: Creating a form element
 * @code
 * // Get the Instance for a new form element and set an action attribute
 * $form = new HtmlFormBasic('test.php');
 * // XHTML determines that the input elements are inline elements of a block element
 * // so we need somthing like a div Block. In this example we use a fieldset
 * $form->addFieldSet();
 * // we can define a label for the input element with reference ID
 * $form->addLabel('Field_1', 'ID_1');
 * // set an input element like a text field. All valid types are supported
 * // you can define further attributes as associative array and set as parameter in correct position
 * $form->addSimpleInput('text', 'Input_1', 'ID_1', 'Value_1', $attrArray);
 * // add a linebreak
 * $form->linebreak();
 * // next label
 * $form->addLabel('Radio_1', 'ID_2');
 * // next element is a radio button
 * $form->addSimpleInput('radio', 'Radio_1', 'ID_2', 'Value_Radio');
 * // add a linebreak
 * $form->linebreak();
 * // Define a select box
 * $form->addSelect('Select_Name', 'ID_3', $attrArray);
 * // now we can also specify an optiongroup
 * $form->addOptionGroup('Group_1', 'ID_4', $attrArray);
 * // define options
 * $form->addOption('Option_Value_1', 'Option_Label_1');
 * $form->addOption('Option_Value_2', 'Option_Label_2');
 * $form->addOption('Option_Value_3', 'Option_Label_3');
 * // end of option group
 * $form->closeOptionGroup();
 * // end of select box
 * $form->closeSelect();
 * // add a linebreak
 * $form->linebreak();
 * // example of a text area
 * $form->addTextArea('Textarea', '4', '4', 'Input please ...', 'ID_5', $attrArray);
 * // close open fieldset block
 * $form->closeFieldSet();
 * // print the form
 * echo $form->getHtmlForm();
 * @endcode
 */
class HtmlFormBasic extends HtmlElement
{
    /**
     * Constructor creates the element
     *
     * @param string $action Optional action attribute of the form
     * @param string $id     Id of the form
     * @param string $method Get/Post (Default "get" if not defined)
     * @param string $event  Optional event handler
     * @param string $script Optional script or function called from event handler
     */
    public function __construct($action = '', $id = '', $method = 'get', $event = '', $script = '')
    {
        parent::__construct('form', '', '', true);

        // set action attribute
        if($action !== '')
        {
            $this->addAttribute('action', $action);
        }

        if($id !== '')
        {
            $this->addAttribute('id', $id);
        }

        if($method !== '')
        {
            $this->addAttribute('method', $method);
        }

        if($event !== '' && $script !== '')
        {
            $this->addAttribute($event, $script);
        }
    }

    /**
     * Add a fieldset.
     * @param string $id     Optional ID
     * @param string $legend Description for optional legend element as string
     */
    public function addFieldSet($id = '', $legend = '')
    {
        $this->addParentElement('fieldset');

        if($legend !== '')
        {
            $this->addLegend($legend);
        }
    }

    /**
     * Add a input field with attribute properties.
     * @param string   $type          Type of input field e.g. 'text'
     * @param string   $name          Name of the input field
     * @param string   $id            Optional ID for the input
     * @param string   $value         Value of the field (Default: empty)
     * @param string[] $arrAttributes Further attributes as array with key/value pairs
     */
    public function addSimpleInput($type, $name, $id = '', $value = '', $arrAttributes = null)
    {
        $this->addElement('input', '', '', '',  true);

        // set all attributes
        $this->addAttribute('type', $type);
        $this->addAttribute('name', $name);

        if($id !== '')
        {
            $this->addAttribute('id', $id);
        }

        $this->addAttribute('value', $value);

        // Check optional attributes in associative array and set all attributes
        if($arrAttributes !== null && is_array($arrAttributes))
        {
            $this->setAttributesFromArray($arrAttributes);
        }

        $this->addData(' ', true);
    }

    /**
     * Add a label to the input field.
     * @param string $string    Value of the label as string
     * @param string $refID
     * @param string $attribute
     */
    public function addLabel($string = '', $refID = '', $attribute = 'for')
    {
        $this->addElement('label');

        if($refID !== '')
        {
            $this->addAttribute($attribute, $refID);
        }
        $this->addData($string);
    }

    /**
     * Add a legend element in current fieldset.
     * @param string $legend Data for the element as string
     */
    public function addLegend($legend)
    {
        $this->addElement('legend', '', '', $legend);
    }

    /**
     * Add inline element into current division.
     * @param string $value    Option value
     * @param string $label    Label of the option
     * @param string $id       Optional Id of the option
     * @param bool   $selected Mark as selected (Default: false)
     * @param bool   $disable  Disable option (optional)
     */
    public function addOption($value, $label, $id = '', $selected = false, $disable = false)
    {
        $this->addElement('option');
        // set attributes
        $this->addAttribute('value', $value);

        if($id !== '')
        {
            $this->addAttribute('id', $id);
        }

        if($selected === true)
        {
            $this->addAttribute('selected', 'selected');
        }

        if($disable === true)
        {
            $this->addAttribute('disabled', 'disabled');
        }
        // add label
        $this->addData($label);
    }

    /**
     * Add an option group.
     * @param string $label         Label of the option group
     * @param string $id            Optional Id of the group
     * @param array  $arrAttributes Further attributes as array with key/value pairs
     * @param bool   $disable       Disable option group (Default: false)
     */
    public function addOptionGroup($label, $id = '', $arrAttributes = null, $disable = false)
    {
        $this->addParentElement('optgroup');

        // set attributes
        $this->addAttribute('label', $label);

        if($id !== '')
        {
            $this->addAttribute('id', $id);
        }

        // Check optional attributes in associative array and set all attributes
        if($arrAttributes !== null && is_array($arrAttributes))
        {
            $this->setAttributesFromArray($arrAttributes);
        }

        if($disable === true)
        {
            $this->addAttribute('disabled', 'disabled');
        }
    }

    /**
     * Add an option group.
     * @param string $name          Name of the select
     * @param string $id            Optional Id of the select
     * @param array  $arrAttributes Further attributes as array with key/value pairs
     * @param bool   $disable       Disable select (Default: false)
     */
    public function addSelect($name, $id = '', $arrAttributes = null, $disable = false)
    {
        $this->addParentElement('select', 'name', $name);

        // set attributes
        if($id !== '')
        {
            $this->addAttribute('id', $id);
        }

        // Check optional attributes in associative array and set all attributes
        if($arrAttributes !== null && is_array($arrAttributes))
        {
            $this->setAttributesFromArray($arrAttributes);
        }

        if($disable === true)
        {
            $this->addAttribute('disabled', 'disabled');
        }
    }

    /**
     * Adds a button to the form.
     * @param string $name  Name of the button
     * @param string $type  Type attribute (Allowed: submit, reset, button (Default: button))
     * @param string $value Value of the button
     * @param string $id    Optional ID for the button
     * @param string $link  If set a javascript click event with a page load to this link
     *                      will be attached to the button.
     */
    public function addSimpleButton($name, $type = 'button', $value, $id = '', $link = '')
    {
        $this->addElement('button');

        if($id !== '')
        {
            $this->addAttribute('id', $id);
        }

        // if link is set then add a onclick event
        if($link !== '')
        {
            $this->addAttribute('onclick', 'self.location.href=\''.$link.'\'');
        }

        $this->addAttribute('name', $name);
        $this->addAttribute('type', $type);
        $this->addData($value);
    }

    /**
     * Add a text area.
     * @param string $name          Name of the text area
     * @param int    $rows          Number of rows
     * @param int    $cols          Number of cols
     * @param string $text          Text as content
     * @param string $id            Optional Id
     * @param array  $arrAttributes Further attributes as array with key/value pairs
     * @param bool   $disable       Disable text area (Default: false)
     */
    public function addTextArea($name, $rows, $cols, $text = '', $id = '', $arrAttributes = null, $disable = false)
    {
        $this->addElement('textarea');

        // set attributes
        $this->addAttribute('name', $name);
        $this->addAttribute('rows', $rows);
        $this->addAttribute('cols', $cols);

        if($id !== '')
        {
            $this->addAttribute('id', $id);
        }

        // Check optional attributes in associative array and set all attributes
        if($arrAttributes !== null && is_array($arrAttributes))
        {
            $this->setAttributesFromArray($arrAttributes);
        }

        if($disable === true)
        {
            $this->addAttribute('disabled', 'disabled');
        }

        $this->addData($text);
    }

    /**
     * @par Close current fieldset.
     */
    public function closeFieldSet()
    {
        $this->closeParentElement('fieldset');
    }

    /**
     * @par Close current option group.
     */
    public function closeOptionGroup()
    {
        $this->closeParentElement('optgroup');
    }

    /**
     * @par Close current select.
     */
    public function closeSelect()
    {
        $this->closeParentElement('select');
    }

    /**
     * Get the full parsed html form
     * @return string Returns the validated html form as string
     */
    public function getHtmlForm()
    {
        return parent::getHtmlElement();
    }
}

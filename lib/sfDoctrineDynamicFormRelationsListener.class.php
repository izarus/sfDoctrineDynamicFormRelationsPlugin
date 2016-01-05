<?php

/**
 * Processes deletion of removed foreign objects.
 *
 * @package    sfDoctrineDynamicFormRelationsPlugin
 * @subpackage record
 * @author     Kris Wallsmith <kris.wallsmith@symfony-project.com>
 * @author     Christian Schaefer <caefer@ical.ly>
 */
class sfDoctrineDynamicFormRelationsListener extends Doctrine_Record_Listener
{
  protected
    $form = null;

  /**
   * Constructor.
   *
   * @param sfForm $form A form
   */
  public function __construct(sfForm $form)
  {
    $this->form = $form;
  }

  /**
   * Pre-save logic.
   *
   * Use preSave instead of preUpdate since the latter depends on the record's
   * state, which isn't necessarily dirty.
   *
   * @see Doctrine_Record_Listener
   */
  public function preSave(Doctrine_Event $event)
  {
    // this listener may have been added several times with a different $form instance
    // but as listeners have a model rather than a record scope we need to filter if
    // this current listener actually matches!
    if($this->form->getObject()->id == $event->getInvoker()->id)
    {
      $this->doPreSave($event->getInvoker(), $this->form);
    }
  }

  protected function doPreSave(Doctrine_Record $record, sfForm $form)
  {
    // loop through relations
    if ($relations = $form->getOption('dynamic_relations'))
    {
      foreach ($relations as $field => $config)
      {
        $collection = $record->get($config['relation']->getAlias());

        // collect form objects for comparison
        $search = array();
        try
        {
          foreach ($form->getEmbeddedForm($field)->getEmbeddedForms() as $i => $embed)
          {
            $search[] = $embed->getObject();
          }
        }
        catch(InvalidArgumentException $e)
        {
          // previously embedded form was removed at the end of form.filter_values as there were no values for it.
          // @see sfDoctrineDynamicFormRelations::correctValidators()
        }

        foreach ($collection as $i => $object)
        {
          $pos = array_search($object, $search, true);
          if (false ===  $pos && $this->filterObject($object, $config['arguments']))
          {
            // if a related object exists in the record but isn't represented
            // in the form, the reference has been removed
            //$collection->remove($i);

            // if the foreign column is a notnull columns, delete the object
            $column = $config['relation']->getTable()->getColumnDefinition($config['relation']->getForeignColumnName());
            if ($object->exists() && isset($column['notnull']) && $column['notnull'])
            {
              $object->delete();
            }
          }
        }
      }
    }
  }

  /**
   * validate the fact that we need to filter the object based on the arguments
   * @param $object
   * @param array $arguments
   * @return bool
   */
  protected function filterObject($object, array $arguments = array())
  {
    if(!empty($arguments))
    {
      foreach ($arguments as $index => $value)
      {
        if ($object->get($index) != $value) {
          return false;
        }
      }
    }
    return true;
  }
}
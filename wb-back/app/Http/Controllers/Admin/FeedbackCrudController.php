<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\FeedbackRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class FeedbackCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class FeedbackCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     * 
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(\App\Models\Feedback::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/feedback');
        CRUD::setEntityNameStrings('feedback', 'feedback');
    }

    /**
     * Define what happens when the List operation is loaded.
     * 
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     * @return void
     */
    protected function setupListOperation()
    {
        CRUD::setFromDb(); // set columns from db columns.
        CRUD::column('productDetails')->type('json');
        CRUD::column('photoLinks')->type('json');
        /**

        /**
         * Columns can be defined using the fluent syntax:
         * - CRUD::column('price')->type('number');
         */
    }

    /**
     * Define what happens when the Create operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(FeedbackRequest::class);
        CRUD::setFromDb(); // set fields from db columns.
        
        CRUD::addField([
            'name' => 'productDetails',
            'type' => 'textarea',
            'label' => 'Product Details',
            'value' => json_encode($this->crud->getCurrentEntry()->productDetails ?? [], JSON_PRETTY_PRINT), // Prettify the JSON for better readability
        ]);
        CRUD::addField([
            'name' => 'photoLinks',
            'type' => 'textarea',
            'label' => 'Photo Links',
            'value' => json_encode($this->crud->getCurrentEntry()->photoLinks ?? [], JSON_PRETTY_PRINT), // Prettify the JSON for better readability
        ]);

        /**
         * Fields can be defined using the fluent syntax:
         * - CRUD::field('price')->type('number');
         */
    }

    /**
     * Define what happens when the Update operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-update
     * @return void
     */
    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }
}

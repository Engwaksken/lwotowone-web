<?php
return [
    'modules'=>[
        'course_modules'=>['title'=>'Course modules','fields'=>['course_id'=>'ref:courses','title'=>'text','position'=>'number','status'=>['draft','published']]],
    ],
    'fields'=>[
        'courses'=>['prerequisite_course_id'=>'optional:ref:courses'],
        'lessons'=>['module_id'=>'optional:ref:course_modules'],
        'resources'=>['lesson_id'=>'optional:ref:lessons'],
    ],
];

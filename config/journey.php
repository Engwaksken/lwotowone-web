<?php
return [
    'modules'=>[
        'course_modules'=>['title'=>'Course modules','fields'=>['course_id'=>'ref:courses','title'=>'text','title_lg'=>'optional:text','position'=>'number','status'=>['draft','published']]],
    ],
    'fields'=>[
        'courses'=>['prerequisite_course_id'=>'optional:ref:courses','title_lg'=>'optional:text','summary_lg'=>'optional:textarea'],
        'programs'=>['title_lg'=>'optional:text','summary_lg'=>'optional:textarea','body_lg'=>'optional:textarea'],
        'assignments'=>['title_lg'=>'optional:text','instructions_lg'=>'optional:textarea'],
        'lessons'=>['module_id'=>'optional:ref:course_modules','title_lg'=>'optional:text','content_body_lg'=>'optional:textarea'],
        'resources'=>['lesson_id'=>'optional:ref:lessons','title_lg'=>'optional:text','description_lg'=>'optional:textarea','content_body_lg'=>'optional:textarea'],
    ],
];

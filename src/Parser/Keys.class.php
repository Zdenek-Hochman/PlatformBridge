<?php

namespace Parser;

enum Keys: string {
    case KEY_BLOCKS = 'blocks';
    case KEY_LAYOUTS = 'layouts';
    case KEY_GENERATORS = 'generators';
    case KEY_SECTIONS = 'sections';
    case KEY_REF = 'ref';
    case KEY_LAYOUT_REF = 'layout_ref';
    case KEY_ID = 'id';
}
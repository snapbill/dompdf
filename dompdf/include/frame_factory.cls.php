<?php
/**
 * @package dompdf
 * @link    http://www.dompdf.com/
 * @author  Benj Carson <benjcarson@digitaljunkies.ca>
 * @license http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 * @version $Id$
 */

/**
 * Contains frame decorating logic
 *
 * This class is responsible for assigning the correct {@link Frame_Decorator},
 * {@link Positioner}, and {@link Frame_Reflower} objects to {@link Frame}
 * objects.  This is determined primarily by the Frame's display type, but
 * also by the Frame's node's type (e.g. DomElement vs. #text)
 *
 * @access private
 * @package dompdf
 */
class Frame_Factory {

  /**
   * Decorate the root Frame
   * 
   * @param $root Frame The frame to decorate
   * @param $dompdf DOMPDF The dompdf instance
   * @return Page_Frame_Decorator
   */
  static function decorate_root(Frame $root, DOMPDF $dompdf) {
    $frame = new Page_Frame_Decorator($root, $dompdf);
    $frame->set_reflower( new Page_Frame_Reflower($frame) );
    $root->set_decorator($frame);
    return $frame;
  }

  /**
   * Decorate a Frame 
   * 
   * @param $root Frame The frame to decorate
   * @param $dompdf DOMPDF The dompdf instance
   * @return Frame_Decorator
   * FIXME: this is admittedly a little smelly...
   */ 
  static function decorate_frame(Frame $frame, DOMPDF $dompdf, Frame $root = null) {
    if ( is_null($dompdf) )
      throw new Exception("foo");
      
    $style = $frame->get_style();
    $display = $style->display;
    
    switch ($display) {
      
    case "block":
      $positioner = "Block";        
      $decorator = "Block";
      $reflower = "Block";
      break;
    
    case "inline-block":
      $positioner = "Inline";
      $decorator = "Block";
      $reflower = "Block";
      break;

    case "inline":
      $positioner = "Inline";
      if ( $frame->is_text_node() ) {
        $decorator = "Text";
        $reflower = "Text";
      } 
      else {
        if ( DOMPDF_ENABLE_CSS_FLOAT && $style->float !== "none" ) {
          $decorator = "Block";
          $reflower = "Block";
        }
        else {
          $decorator = "Inline";
          $reflower = "Inline";
        }
      }
      break;   

    case "table":
      $positioner = "Block";
      $decorator = "Table";
      $reflower = "Table";
      break;
      
    case "inline-table":
      $positioner = "Inline";
      $decorator = "Table";
      $reflower = "Table";
      break;

    case "table-row-group":
    case "table-header-group":
    case "table-footer-group":
      $positioner = "Null";
      $decorator = "Table_Row_Group";
      $reflower = "Table_Row_Group";
      break;
      
    case "table-row":
      $positioner = "Null";
      $decorator = "Table_Row";
      $reflower = "Table_Row";
      break;

    case "table-cell":
      $positioner = "Table_Cell";
      $decorator = "Table_Cell";
      $reflower = "Table_Cell";
      break;
        
    case "list-item":
      $positioner = "Block";
      $decorator  = "Block";
      $reflower   = "Block";
      break;

    case "-dompdf-list-bullet":
      if ( $style->list_style_position === "inside" )
        $positioner = "Inline";
      else        
        $positioner = "List_Bullet";

      if ( $style->list_style_image !== "none" )
        $decorator = "List_Bullet_Image";
      else
        $decorator = "List_Bullet";
      
      $reflower = "List_Bullet";
      break;

    case "-dompdf-image":
      $positioner = "Inline";
      $decorator = "Image";
      $reflower = "Image";
      break;
      
    case "-dompdf-br":
      $positioner = "Inline";
      $decorator = "Inline";
      $reflower = "Inline";
      break;

    default:
      // FIXME: should throw some sort of warning or something?
    case "none":
      $positioner = "Null";
      $decorator = "Null";
      $reflower = "Null";
      break;
    }

    // Handle CSS position
    $position = $style->position;
    
    if ( $position === "absolute" )
      $positioner = "Absolute";

    else if ( $position === "fixed" )
      $positioner = "Fixed";
      
    // Handle nodeName
    $node_name = $frame->get_node()->nodeName;
    
    if ( $node_name === "img" ) {
      $style->display = "-dompdf-image";
      $decorator = "Image";
      $reflower = "Image";
    }
  
    $positioner .= "_Positioner";
    $decorator .= "_Frame_Decorator";
    $reflower .= "_Frame_Reflower";

    $deco = new $decorator($frame, $dompdf);
    
    $deco->set_positioner( new $positioner($deco) );
    $deco->set_reflower( new $reflower($deco) );
    
    if ( $root ) {
      $deco->set_root($root);
    }
    
    if ( $display === "list-item" ) {
      // Insert a list-bullet frame
      $xml = $dompdf->get_dom();
      $node = $xml->createElement("bullet"); // arbitrary choice
      $b_f = new Frame($node);

      $parent_node = $frame->get_node()->parentNode;
      
      if ( $parent_node ) {
        if ( !$parent_node->hasAttribute("dompdf-children-count") ) {
          $xpath = new DOMXPath($xml);
          $count = $xpath->query("li", $parent_node)->length;
          $parent_node->setAttribute("dompdf-children-count", $count);
        }
  
        if ( !$parent_node->hasAttribute("dompdf-counter") ) {
          $index = ($parent_node->hasAttribute("start") ? $parent_node->getAttribute("start")-1 : 0);
        }
        else {
          $index = $parent_node->getAttribute("dompdf-counter");
        }
        
        $index++;
        $parent_node->setAttribute("dompdf-counter", $index);
        
        $node->setAttribute("dompdf-counter", $index);
      }
      
      $new_style = $dompdf->get_css()->create_style();
      $new_style->display = "-dompdf-list-bullet";
      $new_style->inherit($style);
      $b_f->set_style($new_style);
      
      $deco->prepend_child( Frame_Factory::decorate_frame($b_f, $dompdf, $root) );
    }
    
    return $deco;
  }
}

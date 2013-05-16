<?php

  if(!function_exists('checkNum'))
  {
      function checkNum($str)
      {
          if(preg_match(" /^\d+(.\d+)?$/",$str))
          {
              return true;
          }
          else
          {
              return false;
          }
      }
  }
function checkUser1($str)
{
    if(preg_match("/^[a-z0-9][a-z0-9_]{4,13}[a-z0-9]$/",$str))
    {
        return true;
    }
    else
    {
        return false;
    }
}
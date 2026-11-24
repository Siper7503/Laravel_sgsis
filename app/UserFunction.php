<?php

namespace App;

enum UserFunction: string
{
    case DirecteurDse = 'directeur_dse';
    case DirecteurDgss = 'directeur_dgss';
    case Proviseur = 'proviseur';
    case DirecteurEcole = 'directeur_ecole';
}

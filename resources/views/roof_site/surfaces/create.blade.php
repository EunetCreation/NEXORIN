@extends('layouts.app')
@section('title','Add Roof Surface')
@section('page-eyebrow','Roof & site survey')
@section('page-heading','Roof Surface Editor')
@section('content')
@include('roof_site.surfaces.partials.form',[
    'heading'=>'Add Roof Surface',
    'description'=>'Record geometry, exclusions, orientation, shading, and condition for one roof plane.',
    'action'=>route('companies.roof-site.surfaces.store',[$company,$project]),
    'method'=>'POST',
    'submitLabel'=>'Save Roof Surface',
])
@endsection

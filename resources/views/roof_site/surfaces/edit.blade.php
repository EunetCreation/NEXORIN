@extends('layouts.app')
@section('title','Edit Roof Surface')
@section('page-eyebrow','Roof & site survey')
@section('page-heading','Edit Roof Surface')
@section('content')
@include('roof_site.surfaces.partials.form',[
    'heading'=>'Edit '.$surface->name,
    'description'=>'Update the recorded roof geometry and preliminary site assessment.',
    'action'=>route('companies.roof-site.surfaces.update',[$company,$project,$surface]),
    'method'=>'PUT',
    'submitLabel'=>'Update Roof Surface',
])
@endsection

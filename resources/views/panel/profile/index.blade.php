@extends('layouts.web')

@push('styles')

@endpush

@section('content')
    <div class="container-fluid">
        @include('includes.navbar_top')
        @include('includes.navbar_left')

        <div class="page__content">

            <div class="card mt-5">
                <div class="card-header">
                    <h3 class="mb-0"><strong>{{trans('panel.MY_ACCOUNT')}}</strong></h3>
                </div>
                <div class="card-body">
                    <form action="{{ route('panel.profile.store') }}" method="post">
                        @method('post')
                        @csrf
                        <div class="mb-3 row">
                            <label for="formName" class="col-sm-2 col-form-label">{{trans('panel.FULL_NAME')}}</label>
                            <div class="col-sm-10">
                                <input type="text" name="name" placeholder="{{trans('panel.FULL_NAME')}}" class="form-control @error('name') is-invalid @enderror" id="formName" value="{{ auth()->user()->name ?? old('name') }}">
                                @error('name')
                                    <div class="alert alert-danger">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="mb-3 row">
                            <label for="formPHONE" class="col-sm-2 col-form-label">{{trans('panel.TELEPHONE')}}</label>
                            <div class="col-sm-10">
                                <input type="text" name="phone" placeholder="{{trans('panel.Enter_CPF')}}" class="form-control sp_celphones @error('phone') is-invalid @enderror" id="formPHONE" value="{{ auth()->user()->phone ?? old('phone') }}">
                                @error('phone')
                                    <div class="alert alert-danger">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="mb-3 row">
                            <label for="formCPF" class="col-sm-2 col-form-label">CPF</label>
                            <div class="col-sm-10">
                                <input type="text" name="cpf" placeholder="{{trans('panel.Enter_CPF')}}" class="form-control cpf @error('cpf') is-invalid @enderror" id="formCPF" value="{{ auth()->user()->cpf ?? old('cpf') }}">
                                @error('cpf')
                                    <div class="alert alert-danger">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="mb-3 row">
                            <label for="formEmail" class="col-sm-2 col-form-label">E-MAIL</label>
                            <div class="col-sm-10">
                                <input type="text" name="email" readonly placeholder="{{trans('panel.Type_mail')}}" class="form-control @error('email') is-invalid @enderror" id="formEmail" value="{{ auth()->user()->email ?? old('email') }}">
                                @error('email')
                                    <div class="alert alert-danger">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <hr>
                        <div class="mb-3 row">
                            <label for="formEmail" class="col-sm-2 col-form-label">
                                {{trans('panel.CHANGE_PASSWORD')}}
                                <p class="mt-2">
                                    {{trans('panel.change_password')}}
                                </p>
                            </label>
                            <div class="col-sm-10">
                                <input type="passsword" name="old_password" placeholder="{{trans('panel.Old_Password')}}" class="form-control mb-3 @error('old_password') is-invalid @enderror" id="formOldPassword" value="">
                                <input type="passsword" name="new_password" placeholder="{{trans('panel.New_Password')}}" class="form-control @error('new_password') is-invalid @enderror" id="formNewPassword" value="">

                                @error('old_password')
                                    <div class="alert alert-danger">{{ $message }}</div>
                                @enderror

                                @error('new_password')
                                    <div class="alert alert-danger">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="mb-3 mt-5 row">
                            <div class="col-sm-2"></div>
                            <div class="col-sm-10">
                                <button type="submit" class="ui-button button-normal s-conic2 btn">
                                    <div class="button-inner">{{trans('panel.SAVE_PASSWORD')}}</div>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('/assets/js/jquery.mask.min.js') }}"></script>
    <script>
        $('.cpf').mask('000.000.000-00', {reverse: true});

        var SPMaskBehavior = function (val) {
                return val.replace(/\D/g, '').length === 11 ? '(00) 00000-0000' : '(00) 0000-00009';
            },
            spOptions = {
                onKeyPress: function(val, e, field, options) {
                    field.mask(SPMaskBehavior.apply({}, arguments), options);
                }
            };

        $('.sp_celphones').mask(SPMaskBehavior, spOptions);

    </script>
@endpush

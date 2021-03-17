<?

/* PE constants */
define("IMAGE_DOS_SIGNATURE", 0x5A4D);
define("IMAGE_DOS_OFFSET", 0x0);
define("IMAGE_NT_SIGNATURE", 0x4550);
define("IMAGE_FILE_MACHINE_I386", 0x014C);
define("IMAGE_FILE_MACHINE_AMD64", 0x8664);
define("IMAGE_NUMBEROF_DIRECTORY_ENTRIES", 16);

define("IMAGE_DIRECTORY_ENTRY_ARCHITECTURE", 7);
define("IMAGE_DIRECTORY_ENTRY_BASERELOC", 5);
define("IMAGE_DIRECTORY_ENTRY_BOUND_IMPORT", 11);
define("IMAGE_DIRECTORY_ENTRY_COM_DESCRIPTOR", 14);
define("IMAGE_DIRECTORY_ENTRY_DEBUG", 6);
define("IMAGE_DIRECTORY_ENTRY_DELAY_IMPORT", 13);
define("IMAGE_DIRECTORY_ENTRY_EXCEPTION", 3);
define("IMAGE_DIRECTORY_ENTRY_EXPORT", 0);
define("IMAGE_DIRECTORY_ENTRY_GLOBALPTR", 8);
define("IMAGE_DIRECTORY_ENTRY_IAT", 12);
define("IMAGE_DIRECTORY_ENTRY_IMPORT", 1);
define("IMAGE_DIRECTORY_ENTRY_LOAD_CONFIG", 10);
define("IMAGE_DIRECTORY_ENTRY_RESOURCE", 2);
define("IMAGE_DIRECTORY_ENTRY_SECURITY", 4);
define("IMAGE_DIRECTORY_ENTRY_TLS", 9);

define("IMAGE_SIZEOF_SECTION_HEADER", 40);
define("IMAGE_SIZEOF_SHORT_NAME", 8);

define("IMAGE_ORDINAL_FLAG32", 0x80000000);
define("IMAGE_ORDINAL_FLAG64", 0x8000000000000000);

define("IMAGE_REL_BASED_ABSOLUTE", 0);
define("IMAGE_REL_BASED_HIGH", 1);
define("IMAGE_REL_BASED_LOW", 2);
define("IMAGE_REL_BASED_HIGHLOW", 3);
define("IMAGE_REL_BASED_HIGHADJ", 4);
define("IMAGE_REL_BASED_MIPS_JMPADDR", 5);
define("IMAGE_REL_BASED_DIR64", 10);

/* helper functions */
function jump_to($file, $offset) {
    fseek($file, $offset);
}
function parse_little_endian($string) {
    $result = 0;
    for($i = strlen($string) - 1; $i >= 0; --$i) {
        $result = $result << 8;
        $result += ord($string[$i]);
    }
    return $result;
}
function read_byte($file, $offset) {
    jump_to($file, $offset);
    $byte = fread($file, 1);
    return ord($byte);
}
function read_word($file, $offset ) {
    jump_to($file, $offset);
    $word = fread($file, 2);
    return parse_little_endian($word);
}
function read_dword($file, $offset) {
    jump_to($file, $offset);
    $dword = fread($file, 4);
    return parse_little_endian($dword);
}
function read_qword($file, $offset) {
    jump_to($file, $offset);
    $qword = fread($file, 8);
    return parse_little_endian($qword);
}
function read_str($file, $offset) {
    jump_to($file, $offset);
    $str = fread($file, 256);
    return substr($str, 0, strpos($str, "\0"));
}

function make_signed($value) {
    $i = (int)$value;
    if (PHP_INT_SIZE > 4)   // e.g. php 64bit
        if($i & 0x80000000) // is negative
            return $i - 0x100000000;
    return $i;
}

function resolve_rva($raw_addr, $rel_virtual_addr, $virtual_addr) {
    return $raw_addr + ($rel_virtual_addr - $virtual_addr);
}

function make_rva($raw_addr, $abs_addr, $virtual_addr) {
    return ($abs_addr - $raw_addr) + $virtual_addr;
}

function parse_dos_header($file, &$pe) {
    $pe->DosHeader->base = IMAGE_DOS_OFFSET;
    $pe->DosHeader->e_magic = read_word($file, $pe->DosHeader->base + 0x0);
    $pe->DosHeader->e_lfanew = read_dword($file, $pe->DosHeader->base + 0x3C);
}

function parse_file_header($file, &$pe) {
    $pe->NtHeader->FileHeader->base = $pe->NtHeader->base + 4;
    $pe->NtHeader->FileHeader->Machine = read_word($file, $pe->NtHeader->FileHeader->base + 0x0);
    $pe->NtHeader->FileHeader->NumberOfSections = read_word($file, $pe->NtHeader->FileHeader->base + 0x2);
    $pe->NtHeader->FileHeader->TimeDateStamp = read_dword($file, $pe->NtHeader->FileHeader->base + 0x4);
    $pe->NtHeader->FileHeader->SizeOfOptionalHeader = read_word($file, $pe->NtHeader->FileHeader->base + 0x14);
    $pe->NtHeader->FileHeader->Characteristics = read_word($file, $pe->NtHeader->FileHeader->base + 0x16);
}

function IMAGE_SNAP_BY_ORDINAL($pe, $ordinal) {
    return ($ordinal & ($pe->arch == 32 ? IMAGE_ORDINAL_FLAG32 : IMAGE_ORDINAL_FLAG64)) != 0;
}

function parse_imports($file, &$pe) {
    $imports_section = null;

    for($i = 0; $i < count($pe->SectionHeaders); $i++) {
        if (!strcmp($pe->SectionHeaders[$i]->Name, ".rdata")) {
            $imports_section = $pe->SectionHeaders[$i];
            break;
        }
    }

    $import_descriptor = resolve_rva($imports_section->PointerToRawData, $pe->NtHeader->OptionalHeader->DataDirectory[IMAGE_DIRECTORY_ENTRY_IMPORT]->VirtualAddress, $imports_section->VirtualAddress);

    while (read_dword($file, $import_descriptor )) {
        $pe->ImportDescriptors[$i]->base = $import_descriptor;
        $pe->ImportDescriptors[$i]->OriginalFirstThunk = resolve_rva($imports_section->PointerToRawData,read_dword($file, $import_descriptor ), $imports_section->VirtualAddress);
        $pe->ImportDescriptors[$i]->TimeDateStamp = read_dword($file, $import_descriptor + 4 );
        $pe->ImportDescriptors[$i]->ForwarderChain = read_dword($file, $import_descriptor + 8 );
        $pe->ImportDescriptors[$i]->Name = read_str($file, resolve_rva($imports_section->PointerToRawData,read_dword($file, $import_descriptor + 12 ), $imports_section->VirtualAddress));
        $pe->ImportDescriptors[$i]->FirstThunk = resolve_rva($imports_section->PointerToRawData,read_dword($file, $import_descriptor + 16 ), $imports_section->VirtualAddress);

        $thunk_addr = $pe->ImportDescriptors[$i]->OriginalFirstThunk;
        $func_addr = $pe->ImportDescriptors[$i]->FirstThunk;

        if (!$thunk_addr)
            $thunk_addr = $func_addr;

        $j = 0;
        while (read_dword($file, $thunk_addr )) {
            $pe->ImportDescriptors[$i]->Imports[$j]->Name = read_str($file, resolve_rva($imports_section->PointerToRawData, read_dword($file, $thunk_addr) + 2, $imports_section->VirtualAddress));
            $pe->ImportDescriptors[$i]->Imports[$j]->Address = resolve_rva($imports_section->PointerToRawData, read_dword($file, $func_addr), $imports_section->VirtualAddress);

            $thunk_addr += ($pe->arch == 32 ? 4 : 8);
            $func_addr += ($pe->arch == 32 ? 4 : 8);
            $j++;
        }

        $import_descriptor += 20;
        $i++;
    }
}

function parse_relocations($file, &$pe) {
    $relocations_section = null;

    for($i = 0; $i < count($pe->SectionHeaders); $i++) {
        if (!strcmp($pe->SectionHeaders[$i]->Name, ".reloc")) {
            $relocations_section = $pe->SectionHeaders[$i];
            break;
        }
    }
    
    $base_relocations = resolve_rva($relocations_section->PointerToRawData, $pe->NtHeader->OptionalHeader->DataDirectory[IMAGE_DIRECTORY_ENTRY_BASERELOC]->VirtualAddress, $relocations_section->VirtualAddress);

    $i = 0;
    while (read_dword($file, $base_relocations )) {
        $pe->BaseRelocations[$i]->VirtualAddress = read_dword($file, $base_relocations);
        $pe->BaseRelocations[$i]->SizeOfBlock = read_dword($file, $base_relocations + 4);

        if ($pe->BaseRelocations[$i]->SizeOfBlock >= 8) {
            $count = ($pe->BaseRelocations[$i]->SizeOfBlock - 8) >> 1;
			$addr_list = $base_relocations + 8;

			for ($j = 0; $j < $count; $j++) {
				if (read_word($file, $addr_list + $j * 2)) {
                    $pe->BaseRelocations[$i]->Relocations[$j]->value = read_word($file, $addr_list + $j * 2);
                    $pe->BaseRelocations[$i]->Relocations[$j]->offset = $pe->BaseRelocations[$i]->Relocations[$j]->value & 0xFFF;
                    $pe->BaseRelocations[$i]->Relocations[$j]->type = $pe->BaseRelocations[$i]->Relocations[$j]->value >> 12;
                    $pe->BaseRelocations[$i]->Relocations[$j]->base = $pe->BaseRelocations[$i]->VirtualAddress + $pe->BaseRelocations[$i]->Relocations[$j]->offset;
                    //for fixing relocs later
					//PDWORD ptr = (PDWORD)((LPBYTE)LoaderParams->ImageBase + (pIBR->VirtualAddress + (list[i] & 0xFFF)));
					//*ptr += delta;
				}
			}
        }

        $base_relocations += $pe->BaseRelocations[$i]->SizeOfBlock;
        $i++;
    }
}

function parse_data_directories($file, &$pe) {
    for($i = 0; $i < IMAGE_NUMBEROF_DIRECTORY_ENTRIES; $i++) {
        $cur_data_directory_addr = $pe->NtHeader->OptionalHeader->base + 0x60 + $i * 8;
        $pe->NtHeader->OptionalHeader->DataDirectory[$i]->VirtualAddress = read_dword($file, $cur_data_directory_addr );
        $pe->NtHeader->OptionalHeader->DataDirectory[$i]->Size = read_dword($file, $cur_data_directory_addr + 4 );
    }
}

function parse_optional_header($file, &$pe) {
    $pe->NtHeader->OptionalHeader->base = $pe->NtHeader->FileHeader->base + 0x14;
    $pe->NtHeader->OptionalHeader->Magic = read_word($file, $pe->NtHeader->OptionalHeader->base + 0x0);
    $pe->NtHeader->OptionalHeader->MajorLinkerVersion = read_byte($file, $pe->NtHeader->OptionalHeader->base + 0x2);
    $pe->NtHeader->OptionalHeader->MinorLinkerVersion = read_byte($file, $pe->NtHeader->OptionalHeader->base + 0x3);
    $pe->NtHeader->OptionalHeader->SizeOfCode = read_dword($file, $pe->NtHeader->OptionalHeader->base + 0x4);
    $pe->NtHeader->OptionalHeader->SizeOfInitializedData = read_dword($file, $pe->NtHeader->OptionalHeader->base + 0x8);
    $pe->NtHeader->OptionalHeader->SizeOfUninitializedData = read_dword($file, $pe->NtHeader->OptionalHeader->base + 0xC);
    $pe->NtHeader->OptionalHeader->AddressOfEntryPoint = read_dword($file, $pe->NtHeader->OptionalHeader->base + 0x10);
    $pe->NtHeader->OptionalHeader->BaseOfCode = read_dword($file, $pe->NtHeader->OptionalHeader->base + 0x14);

    if ($pe->arch == 32)
        $pe->NtHeader->OptionalHeader->BaseOfData = read_dword($file, $pe->NtHeader->OptionalHeader->base + 0x18);

    $pe->NtHeader->OptionalHeader->ImageBase = $pe->arch == 32 ? read_dword($file, $pe->NtHeader->OptionalHeader->base + 0x1C) : read_qword($file, $pe->NtHeader->OptionalHeader->base + 0x18);
    $pe->NtHeader->OptionalHeader->SectionAlignment = read_dword($file, $pe->NtHeader->OptionalHeader->base + 0x20);
    $pe->NtHeader->OptionalHeader->FileAlignment = read_dword($file, $pe->NtHeader->OptionalHeader->base + 0x24);
    $pe->NtHeader->OptionalHeader->SizeOfImage = read_dword($file, $pe->NtHeader->OptionalHeader->base + 0x38);
    $pe->NtHeader->OptionalHeader->SizeOfHeaders = read_dword($file, $pe->NtHeader->OptionalHeader->base + 0x3C);
    $pe->NtHeader->OptionalHeader->CheckSum = read_dword($file, $pe->NtHeader->OptionalHeader->base + 0x40);
    $pe->NtHeader->OptionalHeader->DllCharacteristics = read_word($file, $pe->NtHeader->OptionalHeader->base + 0x46);
    $pe->NtHeader->OptionalHeader->NumberOfRvaAndSizes = read_dword($file, $pe->NtHeader->OptionalHeader->base + 0x5C);

    // parsing data directories go here
    parse_data_directories($file, $pe);
    
    parse_imports($file, $pe);
    parse_relocations($file, $pe);
}

function parse_sections($file, &$pe) {
    $section_headers_base = $pe->NtHeader->base + ($pe->arch == 32 ? 248 : 264 ) /* sizeof ( IMAGE_NT_HEADERS64 ) */;

    for ($i = 0; $i < $pe->NtHeader->FileHeader->NumberOfSections; $i++) {
        $cur_section_base = $section_headers_base + $i * IMAGE_SIZEOF_SECTION_HEADER;
        $pe->SectionHeaders[$i]->Name = read_str($file, $cur_section_base);
        $pe->SectionHeaders[$i]->VirtualSize = read_dword($file, $cur_section_base + 0x8);
        $pe->SectionHeaders[$i]->VirtualAddress = read_dword($file, $cur_section_base + 0xC);
        $pe->SectionHeaders[$i]->SizeOfRawData = read_dword($file, $cur_section_base + 0x10);
        $pe->SectionHeaders[$i]->PointerToRawData = read_dword($file, $cur_section_base + 0x14);
        $pe->SectionHeaders[$i]->PointerToRelocations = read_dword($file, $cur_section_base + 0x18);
        $pe->SectionHeaders[$i]->PointerToLinenumbers = read_dword($file, $cur_section_base + 0x1C);
        $pe->SectionHeaders[$i]->NumberOfRelocations = read_word($file, $cur_section_base + 0x20);
        $pe->SectionHeaders[$i]->NumberOfLinenumbers = read_word($file, $cur_section_base + 0x22 );
        $pe->SectionHeaders[$i]->Characteristics = read_dword($file, $cur_section_base + 0x24);
    }
}

function parse_nt_header($file, &$pe){
    $pe->NtHeader->base = $pe->Dosheader->base + $pe->DosHeader->e_lfanew;
    $pe->NtHeader->Signature = read_dword($file, $pe->NtHeader->base + 0x0);

    parse_file_header($file, $pe);
    
    switch ($pe->NtHeader->FileHeader->Machine) {
        case IMAGE_FILE_MACHINE_I386: $pe->arch = 32; break;
        case IMAGE_FILE_MACHINE_AMD64: $pe->arch = 64; break;
        default: $pe->arch = 0; break;
    }

    parse_sections($file, $pe);
    parse_optional_header($file, $pe);
}

/* code starts here */
function parse_pe($file_name) {
    $file = fopen($file_name, "rb");

    if(!$file)
        return null;

    $pe = null;

    /* go to beginning of file */
    parse_dos_header($file, $pe);

    if ($pe->DosHeader->e_magic != IMAGE_DOS_SIGNATURE) {
        fclose($file);
        return null;
    }

    parse_nt_header($file, $pe);

    if ($pe->NtHeader->Signature != IMAGE_NT_SIGNATURE || !$pe->arch) {
        fclose($file);
        return null;
    }

    /* close file handle */
    fclose($file);
    
    return $pe;
}

$pe = parse_pe("hack.dll");

if ($pe) {
    $as_json = json_encode($pe);
    echo $as_json;
}
else {
    echo "failed to parse pe executable.";
}
?>
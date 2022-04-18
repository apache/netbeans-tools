/*
 * Licensed to the Apache Software Foundation (ASF) under one
 * or more contributor license agreements.  See the NOTICE file
 * distributed with this work for additional information
 * regarding copyright ownership.  The ASF licenses this file
 * to you under the Apache License, Version 2.0 (the
 * 'License'); you may not use this file except in compliance
 * with the License.  You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * 'AS IS' BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

 /**
  * Convert the typedefinitions bundled with the typescript compiler into the
  * stub files used by NetBeans (module webcommon/javascript2.editor).
  *
  * Usage:
  * - Run 'npm install' to initialize the node modules
  * - Run 'npm run generate' to start the build process
  *
  * The resulting zip files are placed in the 'out' directory. The generated
  * sources can be found in the child folders of the 'out' directory.
  */

import ts from 'typescript';
import process from 'process';
import fs from 'fs';
import JSZip from 'jszip';

/*******************************************************************************
 * Datastructures to hold type data while parsing
 ******************************************************************************/

class TypeData {
    modules: Map<string,ModuleInfo> = new Map();

    getModule(moduleName: string): ModuleInfo {
        if(! this.modules.has(moduleName)) {
            const mi = new ModuleInfo();
            this.modules.set(moduleName, mi);
        }
        return this.modules.get(moduleName)!;
    }
}

class ModuleInfo {
    aliases: Map<string,ts.TypeNode> = new Map();
    classes: Map<string,ClassInfo> = new Map();
    functions: Map<string,MemberInfo> = new Map();
    variables: Map<string,MemberInfo> = new Map();

    public getClass(className: string): ClassInfo {
        if (!this.classes.has(className)) {
            const ci = new ClassInfo();
            this.classes.set(className, ci);
        }
        return this.classes.get(className)!;
    }
}

class ClassInfo {
    doc: string | undefined;
    inherits: string[] = [];
    constructorInfo: MemberInfo[] = [];
    properties: Map<string,MemberInfo> = new Map();
    file: string[] = [];

    public addInterits(ancestor: string) {
        if(this.inherits.indexOf(ancestor) < 0) {
            this.inherits.push(ancestor);
        }
    }

    public addFile(file: string) {
        if(this.file.indexOf(file) < 0) {
            this.file.push(file);
        }
    }
}

class MemberInfo {
    constructor(callable: boolean, returnType: ts.TypeNode, doc: string, file: string, parameters: {name: string, type: ts.TypeNode, doc: string, optional: boolean}[] | undefined = undefined) {
        this.callable = callable;
        this.returnType = returnType;
        this.doc = doc;
        this.parameters = parameters ? parameters : [];
        this.file = file;
    };

    doc: string;
    returnType: ts.TypeNode;
    callable: boolean;
    parameters: {name: string, type: ts.TypeNode, doc: string, optional: boolean}[];
    file: string;
}

class TypeInfo {
    constructor(jsType: string, jsDocType: string[], addDoc: string | undefined = undefined) {
        this.addDoc = addDoc;
        this.jsdocType = jsDocType;
        this.jsType = jsType;
    }

    /**
     * Original definition of the type
     */
    addDoc: string | undefined;
    /**
     * JS mapping of the type definition
     */
    jsType: string;
    /**
     * JSDoc mapping of the type definition
     */
    jsdocType: string[] = [];

    toJsDocDeclaration(): string {
        return this.jsdocType.length == 1 ? this.jsdocType[0] : ('(' + this.jsdocType.join(' | ') + ')');
    }
}

/**
 * Merge supplied arguments using the separator. False arguements are removed
 * and not merged into the final string
 */
function join(separator: string, ...args: Array<string|undefined>) {
    return args
        .filter(arg => arg && arg.trim() !== '')
        .join(separator);
}

/**
 * Extract TypeInfo from the supplied type node
 */
function toTypeName(propertyType: ts.Node | undefined, moduleInfo: ModuleInfo, parentType: string | undefined = undefined, originalDefinition: string | undefined = undefined): TypeInfo {
    if (!propertyType) {
        return new TypeInfo('undefined', ['undefined']);
    }
    let result = propertyType.getText();
    let arrayDeep = 0;
    while (propertyType && propertyType.kind === ts.SyntaxKind.ArrayType) {
        arrayDeep++;
        propertyType = (<ts.ArrayTypeNode>propertyType).elementType;
    }
    if (arrayDeep > 0 && propertyType.kind === ts.SyntaxKind.TypeReference) {
        let realPropertyType = (<ts.TypeReferenceNode>propertyType).typeName;
        return new TypeInfo('new Array()',
            [(realPropertyType.kind === ts.SyntaxKind.QualifiedName
                ? realPropertyType.getText()
                : 'text' in realPropertyType
                    ? realPropertyType.text
                    : realPropertyType) + '[]'.repeat(arrayDeep)], result);
    }
    if (propertyType.kind === ts.SyntaxKind.AnyKeyword) {
        return new TypeInfo('new Object()', ['Object'], originalDefinition);
    } else if (propertyType.kind === ts.SyntaxKind.StringKeyword) {
        return new TypeInfo('new String()', ['String'], originalDefinition);
    } else if (propertyType.kind === ts.SyntaxKind.NumberKeyword) {
        return new TypeInfo('new Number()', ['Number'], originalDefinition);
   } else if (propertyType.kind === ts.SyntaxKind.UnknownKeyword) {
        return new TypeInfo('undefined', ['Object'], originalDefinition);
    } else if (propertyType.kind === ts.SyntaxKind.BooleanKeyword) {
        return new TypeInfo('new Boolean()', ['Boolean'], originalDefinition);
    } else if (propertyType.kind === ts.SyntaxKind.VoidKeyword) {
        return new TypeInfo('undefined', ['undefined'], originalDefinition);
    } else if (propertyType.kind === ts.SyntaxKind.ObjectKeyword) {
        return new TypeInfo('new Object()', ['Object'], originalDefinition);
    } else if (propertyType.kind === ts.SyntaxKind.UndefinedKeyword) {
        return new TypeInfo('undefined', ['undefined'], originalDefinition);
    } else if (propertyType.kind === ts.SyntaxKind.LiteralType) {
        return new TypeInfo(result, [result], originalDefinition);
    } else if (propertyType.kind === ts.SyntaxKind.BigIntKeyword) {
        return new TypeInfo('BigInt(0)', ['BigInt'], originalDefinition);
    } else if (parentType && propertyType.kind === ts.SyntaxKind.ThisType) {
        return new TypeInfo('new ' + parentType + '()', [parentType], originalDefinition);
   } else if (propertyType.kind === ts.SyntaxKind.SymbolKeyword) {
        return new TypeInfo('new Symbol()', ['Symbol'], originalDefinition);
    } else if (result === 'unique symbol') {
        return new TypeInfo('new Symbol()', ['Symbol'], originalDefinition);
    } else if (propertyType.kind === ts.SyntaxKind.FunctionType) {
        return new TypeInfo('new Function()', ['Function'],  originalDefinition ? originalDefinition : result);
    } else if (propertyType.kind === ts.SyntaxKind.ConstructorType) {
        return new TypeInfo('new Function()', ['Function'],  originalDefinition ? originalDefinition : result);
    } else if (propertyType.kind === ts.SyntaxKind.TupleType) {
        return new TypeInfo('new Object()', ['Object'], originalDefinition ? originalDefinition : result);
    } else if (propertyType.kind === ts.SyntaxKind.TypeLiteral) {
        // XXXX
        return new TypeInfo('new Object()', ['Object'], originalDefinition ? originalDefinition : result);
    } else if (propertyType.kind === ts.SyntaxKind.FirstTypeNode) {
        return new TypeInfo('new Boolean()', ['Boolean'], originalDefinition ? originalDefinition : result);
    } else if (propertyType.kind === ts.SyntaxKind.TypeQuery) {
        const entityName = (propertyType as ts.TypeQueryNode).exprName;
        let typeName = 'undefined';
        if(entityName.kind == ts.SyntaxKind.Identifier) {
            typeName = (entityName as ts.Identifier).text;
        } else if (entityName.kind == ts.SyntaxKind.QualifiedName) {
            typeName = (entityName as ts.QualifiedName).getText();
        }
        return new TypeInfo('new ' + typeName + '()', [typeName], originalDefinition ? originalDefinition : result);
    } else if (propertyType.kind === ts.SyntaxKind.TypeOperator) {
        return toTypeName((propertyType as ts.TypeOperatorNode).type, moduleInfo, parentType, originalDefinition ? originalDefinition : result);
    } else if (propertyType.kind === ts.SyntaxKind.TypeReference) {
        let realPropertyType = (<ts.TypeReferenceNode>propertyType).typeName;
        if (moduleInfo.aliases.has(realPropertyType.getText())) {
            return toTypeName(
                            moduleInfo.aliases.get(realPropertyType.getText())!,
                            moduleInfo,
                            parentType,
                            originalDefinition ? originalDefinition : realPropertyType.getText()
                        );
        } else {
            const type = checker.getTypeAtLocation(propertyType);
            const symbol = type.symbol || type.aliasSymbol;
            let jsType = 'new Object()';
            let jsdocType = 'Object';
            if (symbol) {
                const decls = symbol.getDeclarations() as ts.Declaration[];
                let referencesInterface: boolean = false;
                decls.forEach(d => {
                    if (d.kind == ts.SyntaxKind.InterfaceDeclaration) {
                        referencesInterface = true;
                    }
                });
                if (referencesInterface) {
                    jsType = 'new ' + realPropertyType.getText() + '()';
                    jsdocType = realPropertyType.getText();
                    if ((!moduleInfo.classes.has(jsdocType)) && (moduleInfo.aliases.has(jsdocType))) {
                        return toTypeName(
                            moduleInfo.aliases.get(jsdocType)!,
                            moduleInfo,
                            parentType,
                            originalDefinition ? originalDefinition : realPropertyType.getText()
                        );
                    }
                }
            } else if (type.flags == ts.TypeFlags.Number) {
                jsType = 'new Number()';
                jsdocType = 'Number';
            }
            return new TypeInfo(jsType, [jsdocType], originalDefinition ? originalDefinition : realPropertyType.getText());
        }
    } else if (propertyType.kind === ts.SyntaxKind.IndexedAccessType && result === 'ArrayBufferTypes[keyof ArrayBufferTypes]') {
        return new TypeInfo('new ArrayBuffer()', ['ArrayBuffer'], originalDefinition ? originalDefinition : result);
    } else if (propertyType.kind === ts.SyntaxKind.UnionType) {
        const types = (<ts.TypeNode[]> ((propertyType as any).types))
            .filter(t => t.kind !== ts.SyntaxKind.UndefinedKeyword && !(t.kind === ts.SyntaxKind.LiteralType && t.getText() === 'null') && t.kind !== ts.SyntaxKind.TypeQuery);
        let reducedTypeInfo: TypeInfo | undefined = undefined;
        if (types.length == 1) {
            reducedTypeInfo = toTypeName(types[0], moduleInfo, parentType, originalDefinition ? originalDefinition : result);
        }

        let jsdocType = (<ts.TypeNode[]> ((propertyType as any).types))
            .map(typeNode => toTypeName(typeNode, moduleInfo, parentType, originalDefinition ? originalDefinition : result))
            .map(ti => ti.jsdocType)
            .flat();

        if(reducedTypeInfo) {
            return new TypeInfo(reducedTypeInfo.jsType, jsdocType, originalDefinition ? originalDefinition : result);
        } else {
            return new TypeInfo('new Object()',  jsdocType, originalDefinition ? originalDefinition : result);
        }
    } else if (propertyType.kind === ts.SyntaxKind.IntersectionType) {
        const types = (<ts.TypeNode[]> ((propertyType as any).types))
            .filter(t => t.kind !== ts.SyntaxKind.UndefinedKeyword && !(t.kind === ts.SyntaxKind.LiteralType && t.getText() === 'null') && t.kind !== ts.SyntaxKind.TypeQuery);
        if (types.length == 1) {
            return toTypeName(types[0], moduleInfo, parentType, originalDefinition ? originalDefinition : result);
        }
        return new TypeInfo('new Object()', ['Object'], originalDefinition ? originalDefinition : result);
    } else if (propertyType.kind == ts.SyntaxKind.ParenthesizedType) {
        return toTypeName((propertyType as ts.ParenthesizedTypeNode).type, moduleInfo, parentType, originalDefinition ? originalDefinition : result);
    } else if (propertyType.kind == ts.SyntaxKind.ConditionalType && parentType === 'SubtleCrypto') {
        return toTypeName(moduleInfo.aliases.get('KeyFormat'), moduleInfo, parentType, originalDefinition ? originalDefinition : result);
    } else if (propertyType.kind == ts.SyntaxKind.MappedType && (parentType === 'ResponseInit' || parentType === 'RequestInit' || parentType === 'PushSubscriptionJSON')) {
        return new TypeInfo('{}', ['Object.<String,String>'], originalDefinition ? originalDefinition : result);
    } else if (propertyType.kind == ts.SyntaxKind.MappedType && (parentType === 'AudioWorkletNodeOptions')) {
        return new TypeInfo('{}', ['Object.<String,Number>'], originalDefinition ? originalDefinition : result);
    } else if (propertyType.kind == ts.SyntaxKind.MappedType && (parentType === 'Object')) {
        return new TypeInfo('new Object()', ['Object'], originalDefinition ? originalDefinition : result);
    } else {
        console.log('Unhandled type code: ' + ts.SyntaxKind[propertyType.kind] + ' / ' + result + ' / ' + parentType);
        return new TypeInfo('undefined', ['Object'], originalDefinition ? originalDefinition : result);
    }
}

/**
 * Extract the string version of the member name from the property name node.
 */
function propertyNameToString(propertyName: ts.PropertyName): string {
    switch (propertyName.kind) {
        case ts.SyntaxKind.Identifier: return '.' + (propertyName as ts.Identifier).getText();
        case ts.SyntaxKind.StringLiteral: return '[' + (propertyName as ts.StringLiteral).getText() + ']';
        case ts.SyntaxKind.ComputedPropertyName: return (propertyName as ts.ComputedPropertyName).getText();
        default: 'UNHANDLED: ' + ts.SyntaxKind[propertyName.kind];
    }
    return 'TS KAPUT';
}


/*******************************************************************************
 * Extract the type data from the typescript definition by running a visitor
 * over the AST generated by the typescript parser.
 ******************************************************************************/

let result = new TypeData();

let visit = function(sourceFile: ts.SourceFile, modulePrefix: string, iface: string) {
    return (node: ts.Node) => {
        switch (node.kind) {
            // Handle type aliases so that they can be resolved after parsing is done
            case ts.SyntaxKind.TypeAliasDeclaration:
                result.getModule(modulePrefix).aliases.set(
                    (node as ts.TypeAliasDeclaration).name.getText(),
                    (node as ts.TypeAliasDeclaration).type
                );
                break;
            // Handle variable declarations - they are assumed to be instances
            // declared at the module level
            case ts.SyntaxKind.VariableStatement:
                {
                    (<ts.VariableStatement>node).declarationList.declarations.forEach(decl => {
                        let declName = decl.name.getText();
                        if (declName !== '.prototype' && declName !== '.constructor') {
                            let propertySymbol = checker.getSymbolAtLocation(decl);
                            let propertydoc = '';
                            if (propertySymbol) {
                                propertydoc = ts.displayPartsToString(propertySymbol.getDocumentationComment(checker));
                            }

                            const propertyInfo = new MemberInfo(
                                false,
                                decl.type!,
                                propertydoc,
                                sourceFile.fileName
                            );

                            result.getModule(modulePrefix).variables.set(declName, propertyInfo);
                        };
                    });
                }
                break;
            // Handle function declarations - they are assumed to be unbound
            // functions declared at the module level
            case ts.SyntaxKind.FunctionDeclaration:
                if ((<ts.FunctionDeclaration>node).name) {
                    let functionName = propertyNameToString((<ts.FunctionDeclaration>node).name!);
                    let functionSymbol = ((node as any).symbol as ts.Symbol);

                    const parameters: { name: string, type: ts.TypeNode, doc: string, optional: boolean }[] = [];

                    for (const param of (<ts.MethodSignature>node).parameters) {
                        if (param.name.getText() == 'this') {
                            continue;
                        }
                        let paramSymbol = ((param as any).symbol) as ts.Symbol;
                        parameters.push({
                            name: param.name.getText(),
                            type: param.type!,
                            doc: ts.displayPartsToString(paramSymbol.getDocumentationComment(checker)),
                            optional: !!param.questionToken
                        });
                    }

                    const functionInfo = new MemberInfo(
                        true,
                        (<ts.PropertySignature>node).type!,
                        ts.displayPartsToString(functionSymbol.getDocumentationComment(checker)),
                        sourceFile.fileName,
                        parameters
                    );

                    result.getModule(modulePrefix).functions.set(functionName, functionInfo);
                }
                break;
            // Handle constructors
            case ts.SyntaxKind.ConstructSignature:
                const parameters: { name: string, type: ts.TypeNode, doc: string, optional: boolean }[] = [];

                for (const param of (<ts.ConstructSignatureDeclaration>node).parameters) {
                    if (param.name.getText() == 'this') {
                        continue;
                    }
                    let paramSymbol = ((param as any).symbol) as ts.Symbol;
                    parameters.push({
                        name: param.name.getText(),
                        type: param.type!,
                        doc: ts.displayPartsToString(paramSymbol.getDocumentationComment(checker)),
                        optional: !!param.questionToken
                    });
                }

                let ifaceBase = iface;
                if(iface.endsWith('Constructor')) {
                    ifaceBase = iface.substring(0, iface.length - 11);
                }

                const mi = new MemberInfo(
                    true,
                     (<ts.ConstructSignatureDeclaration>node).type!,
                     '',
                     sourceFile.fileName,
                     parameters
                );

                result.getModule(modulePrefix).getClass(ifaceBase).addFile(sourceFile.fileName);
                result.getModule(modulePrefix).getClass(ifaceBase).constructorInfo.push(mi);

                break;
            // Handle methods
            case ts.SyntaxKind.MethodSignature:
                let functionName = propertyNameToString((<ts.MethodSignature>node).name);
                if (functionName !== '.prototype' && functionName !== '.constructor') {
                    let functionSymbol = ((node as any).symbol as ts.Symbol);

                    const parameters: { name: string, type: ts.TypeNode, doc: string, optional: boolean }[] = [];

                    for (const param of (<ts.MethodSignature>node).parameters) {
                        if (param.name.getText() == 'this') {
                            continue;
                        }
                        let paramSymbol = ((param as any).symbol) as ts.Symbol;
                        parameters.push({
                            name: param.name.getText(),
                            type: param.type!,
                            doc: ts.displayPartsToString(paramSymbol.getDocumentationComment(checker)),
                            optional: !!param.questionToken
                        });
                    }

                    const functionInfo = new MemberInfo(
                        true,
                        (<ts.PropertySignature>node).type!,
                        ts.displayPartsToString(functionSymbol.getDocumentationComment(checker)),
                        sourceFile.fileName,
                        parameters
                    );

                    result.getModule(modulePrefix).getClass(iface).addFile(sourceFile.fileName);
                    result.getModule(modulePrefix).getClass(iface).properties.set(functionName, functionInfo);
                }
                break;
            // Handle properties
            case ts.SyntaxKind.PropertySignature:
                let propertyName = propertyNameToString((<ts.PropertySignature>node).name);
                if (propertyName !== '.prototype' && propertyName !== '.constructor') {
                    let propertySymbol = checker.getSymbolAtLocation(node);
                    let propertydoc = '';
                    if (propertySymbol) {
                        propertydoc = ts.displayPartsToString(propertySymbol.getDocumentationComment(checker));
                    }

                    const propertyInfo = new MemberInfo(
                        false,
                        (<ts.PropertySignature>node).type!,
                        propertydoc,
                        sourceFile.fileName
                    );

                    result.getModule(modulePrefix).getClass(iface).addFile(sourceFile.fileName);
                    result.getModule(modulePrefix).getClass(iface).properties.set(propertyName, propertyInfo);
                }
                break;
            // Handle module declarations - only handled to extract the name of the module
            case ts.SyntaxKind.ModuleDeclaration:
                let moduleName = (<ts.ModuleDeclaration>node).name.text;
                modulePrefix = moduleName;
                break;
            // Handle interface declaration
            case ts.SyntaxKind.InterfaceDeclaration:
                iface = (<ts.InterfaceDeclaration>node).name.text;
                break;

            default:
        }
        switch (node.kind) {
            // For modules decend into their children
            case ts.SyntaxKind.ModuleBlock:
                ts.forEachChild(node, visit(sourceFile, modulePrefix, iface));
                break;
            case ts.SyntaxKind.ModuleDeclaration:
                ts.forEachChild(node, visit(sourceFile, modulePrefix, iface));
                modulePrefix = '';
                break;
            // For interfaces decend into their children and extract the inheritence tree
            case ts.SyntaxKind.InterfaceDeclaration:
                ts.forEachChild(node, visit(sourceFile, modulePrefix, iface));
                const id = node as ts.InterfaceDeclaration;
                let idSymbol = ((id as any).symbol) as ts.Symbol;
                let idDoc = '';
                if (idSymbol) {
                    idDoc = ts.displayPartsToString(idSymbol.getDocumentationComment(checker));
                    result.getModule(modulePrefix).getClass(iface).doc = idDoc;
                }
                result.getModule(modulePrefix).getClass(iface).addFile(sourceFile.fileName);
                if (id.heritageClauses) {
                    id.heritageClauses.forEach(hc => {
                        hc.types.forEach(t => result.getModule(modulePrefix).getClass(iface).addInterits(t.expression.getText()));
                    });
                }
                iface = '';
                break;
        }
    }
};

// Create the TS programm from the esnext definition - that definition includes
// all relevant definitions
let program = ts.createProgram(['node_modules/typescript/lib/lib.esnext.full.d.ts'], {});
let checker = program.getTypeChecker();

program.getSourceFiles().forEach(sourceFile => {
    if(/node_modules\/typescript\/lib\/.*\.ts$/.test(sourceFile.fileName)) {
        ts.forEachChild(sourceFile, visit(sourceFile, '', ''));
    }
});

/*******************************************************************************
 * The extracted types are iterated and converted to JS source
 ******************************************************************************/

function outputMember( outputName: string, memberInfo: MemberInfo, classes: ModuleInfo, ifaceName: string | undefined = undefined): string {
    let result = '';
    result += '//Source: ' + memberInfo.file + '\n';
    result += '/**\n';
    result += memberInfo.doc;
    result += '\n\n';
    for (const p of memberInfo.parameters) {
        const pTypeInfo = toTypeName(p.type, classes, ifaceName);
        result += '@param {';
        result += pTypeInfo.toJsDocDeclaration();
        result += '} ';
        if (p.optional) {
            result += '[';
        }
        result += p.name;
        if (p.optional) {
            result += ']';
        }
        const doc = join(' - ', pTypeInfo.addDoc, p.doc);
        if (doc) {
            result += ' ';
            result += doc;
        }
        result += '\n';
    }
    result += '@returns {';

    let returnInfo = toTypeName(memberInfo.returnType, classes, ifaceName);
    result += returnInfo.toJsDocDeclaration();
    result += '}';
    if (returnInfo.addDoc && returnInfo.addDoc != returnInfo.toJsDocDeclaration()) {
        result += ' ';
        result += returnInfo.addDoc;
    }
    result += '\n';
    result += '**/\n';
    result += outputName + ' = ' + (memberInfo['callable'] ? 'function(' + (memberInfo.parameters.filter(p => !p.optional).map(p => p.name).join(', ')) + ') {}' : returnInfo.jsType) + ';\n\n';
    return result;
}

function outputClassInfo(baseName: string, classInfo: ClassInfo, moduleInfo: ModuleInfo, modules: TypeData, staticDecl: boolean, ifaceName: string): string {
    let result = '';
    classInfo.properties.forEach((memberInfo, method) => {
        const outputName = baseName + method;
        result += outputMember(outputName, memberInfo, moduleInfo, ifaceName);
    });

    classInfo.inherits.forEach(ancestor => {

        if(moduleInfo.classes.has(ancestor)) {
            result += outputClassInfo(baseName, moduleInfo.classes.get(ancestor)!, moduleInfo, modules, staticDecl, ifaceName);
        } else if (modules.modules.has('') && modules.getModule('').classes.has(ancestor)) {
            result += outputClassInfo(baseName, modules.getModule('').getClass(ancestor), modules.getModule(''), modules, staticDecl, ifaceName);
        } else {
            process.stderr.write('Ancestor not found: ' + ancestor + ' for ' + baseName + '\n');
        }
    });
    return result;
}

/**
 * Filter the output by only outputting type originating from the subset
 * defined in this function. Definitions that are not targetted at either core
 * js or the web related.
 */
function doOutput(sourceFiles: string[]): string[] {
    const result: string[] = [];
    for(const sourceFile of sourceFiles) {
        if(/\/typescript\/lib\/lib\.es(\d+|next)\..*d.ts/.test(sourceFile) && result.indexOf('core') < 0) {
            result.push('core');
        } else if(/\/typescript\/lib\/lib\.dom\..*d.ts/.test(sourceFile) && result.indexOf('dom') < 0) {
            result.push('dom');
        } else if(/\/typescript\/lib\/lib\.webworker\..*d.ts/.test(sourceFile) && result.indexOf('dom') < 0) {
            result.push('dom');
        }
    }
    return result;
}

const modulesCreated: string[] = [];
const objectsCreated: string[] = [];

fs.rmdirSync('out', {recursive: true});
fs.mkdirSync('out');
fs.mkdirSync('out/core');
fs.mkdirSync('out/dom');

result.modules.forEach(
    (moduleInfo, module) => {

        if(module !== '' && modulesCreated.indexOf(module) < 0) {
            const sources: string[] = [];
            moduleInfo.classes.forEach((classInfo, className) => {
                classInfo.file.forEach(sourceFile => {
                    if(sources.indexOf(sourceFile) < 0) {
                        sources.push(sourceFile);
                    }
                });
            });
            moduleInfo.functions.forEach(memberInfo => {
                if (sources.indexOf(memberInfo.file) < 0) {
                    sources.push(memberInfo.file);
                }
            });
            moduleInfo.variables.forEach(memberInfo => {
                if (sources.indexOf(memberInfo.file) < 0) {
                    sources.push(memberInfo.file);
                }
            });

            modulesCreated.push(module);

            const targets = doOutput(sources);
            if(targets.length == 1) {
                fs.appendFileSync('out/' + targets[0] + '/' + module + '._.js', module + ' = function() {};\n\n', {encoding: 'utf-8'});
            } else {
                throw 'Module in multiple areas: ' + module;
            }
        }

        moduleInfo.variables.forEach((memberInfo, memberName) => {
            if(moduleInfo.classes.has(memberName)) {
                return;
            }
            let name = memberName;
            if(module === '' && name.startsWith('.')) {
                name = name.substring(1);
            }
            const targets = doOutput([memberInfo.file]);
            if(targets.length > 0) {
                fs.appendFileSync('out/' + targets[0] + '/' + module + '._.js', outputMember(module + name, memberInfo, moduleInfo));
            }
        });

        moduleInfo.functions.forEach((memberInfo, memberName) => {
            let name = memberName;
            if(module === '' && name.startsWith('.')) {
                name = name.substring(1);
            }
            const targets = doOutput([memberInfo.file]);
            if(targets.length > 0) {
                fs.appendFileSync('out/' + targets[0] + '/' + module + '._.js', outputMember(module + name, memberInfo, moduleInfo));
            }
        });

        moduleInfo.classes.forEach((classInfo, iface) => {
            let targets = doOutput(classInfo.file);
            if(targets.length == 0) {
                return;
            } else if (targets.length > 1) {
                console.log('Class in multiple areas: ' + iface);
                if(targets.indexOf('core') >= 0) {
                    targets = ['core'];
                }
            }
            let ifaceName = iface;
            if (iface.endsWith('Constructor')) {
                ifaceName = iface.substring(0, iface.length - 11);
            }
            let targetFile;
            if(module === '') {
                targetFile = 'out/' + targets[0] + '/_.' + ifaceName + '.js';
            } else {
                targetFile = 'out/' + targets[0] + '/' + module + '.' + (ifaceName === '' ? '_' : ifaceName) + '.js';
            }
            const qualifiedIfaceName = (module === '' ? '' : (module + '.')) + ifaceName;
            if(ifaceName != '' && qualifiedIfaceName.trim() !== '' && objectsCreated.indexOf(qualifiedIfaceName) < 0) {
                let result = '';
                const constructorInfos = moduleInfo.classes.get(ifaceName)!.constructorInfo
                    .filter(ci => doOutput([ci.file]))
                    .sort((a, b) => b.parameters.length - a.parameters.length);
                if (constructorInfos.length > 0) {
                    const constructorInfo = constructorInfos[0];
                    result += '//Source: ' + constructorInfo.file + '\n';
                    result += '/**\n';
                    if(moduleInfo.classes.has(ifaceName) && moduleInfo.classes.get(ifaceName)!.doc) {
                        result += moduleInfo.classes.get(ifaceName)!.doc;
                    }
                    result += '\n';
                    if(moduleInfo.classes.has(ifaceName + 'Constructor') && moduleInfo.classes.get(ifaceName + 'Constructor')!.doc) {
                        result += moduleInfo.classes.get(ifaceName + 'Constructor')!.doc;
                    }
                    result += '\n';

                    for(const ci of constructorInfos) {
                        let nonOptional: number;
                        for(nonOptional = 0; nonOptional < ci.parameters.length; nonOptional++) {
                            if(ci.parameters[nonOptional].optional) {
                                break;
                            }
                        }
                        for(let i = nonOptional; i <= ci.parameters.length; i++) {
                            result += '@example new ' + qualifiedIfaceName + '(';
                            for(let j = 0; j < i; j++) {
                                if(j != 0) {
                                    result += ', ';
                                }
                                result += ci.parameters[j].name;
                                result += ': ';
                                const paramType = toTypeName(ci.parameters[j].type, moduleInfo, qualifiedIfaceName);
                                result += paramType.addDoc ? paramType.addDoc : paramType.toJsDocDeclaration();
                            }
                            result += ')\n';
                        }
                    }

                    result += '\n';

                    for (const p of constructorInfo.parameters) {
                        const pTypeInfo = toTypeName(p.type, moduleInfo, qualifiedIfaceName);
                        result += '@param {';
                        result += pTypeInfo.toJsDocDeclaration();
                        result += '} ';
                        if (p.optional) {
                            result += '[';
                        }
                        result += p.name;
                        if (p.optional) {
                            result += ']';
                        }
                        const doc = join(' - ', pTypeInfo.addDoc, p.doc);
                        if (doc) {
                            result += ' ';
                            result += doc;
                        }
                        result += '\n';
                    }

                    result += '@returns {' + qualifiedIfaceName + '}\n';
                    result += '**/\n';
                    result += qualifiedIfaceName + ' = function(' + (constructorInfo.parameters.filter(p => !p.optional).map(p => p.name).join(', ')) + ') {};\n\n';
                } else {
                    result += '/**\n';
                    if(moduleInfo.classes.has(ifaceName) && moduleInfo.classes.get(ifaceName)!.doc) {
                        result += moduleInfo.classes.get(ifaceName)!.doc;
                    }
                    result += '\n';
                    if(moduleInfo.classes.has(ifaceName + 'Constructor') && moduleInfo.classes.get(ifaceName + 'Constructor')!.doc) {
                        result += moduleInfo.classes.get(ifaceName + 'Constructor')!.doc;
                    }
                    result += '\n';
                    result += '@returns {' + qualifiedIfaceName + '}\n';
                    result += '*/\n';
                    result += qualifiedIfaceName + ' = function() {};\n\n';
                }
                fs.appendFileSync(targetFile, result, {encoding: 'utf-8'});
                objectsCreated.push(qualifiedIfaceName);
            }
            const staticDecl = iface.endsWith('Constructor') || iface === 'Math';
            let baseName =
                (module === '' ? '' : (module + '.'))
                + (ifaceName === '' ? '' : (ifaceName + '.'))
                + (staticDecl ? '' : 'prototype.');
            baseName = baseName.substring(0, baseName.length - 1);

            fs.appendFileSync(targetFile, outputClassInfo(baseName, classInfo, moduleInfo, result, staticDecl, ifaceName), {encoding: 'utf-8'});
        })
    }
);

/*******************************************************************************
 * Bundle the generated JS sources into the final corestubs.zip file
 ******************************************************************************/

for (const subdir of ['core', 'dom']) {
    let dirent: fs.Dirent | null;
    let zip = new JSZip();
    let dir = zip.folder('jsstubs');

    const outDir = fs.opendirSync('out/' + subdir);
    while ((dirent = outDir.readSync()) != null) {
        const buffer = fs.readFileSync('out/' + subdir + '/' + dirent.name);
        dir!.file(dirent.name, buffer);
    }
    outDir.closeSync();

    zip
        .generateNodeStream({ streamFiles: true, compression: 'DEFLATE' })
        .pipe(fs.createWriteStream('out/' + subdir + '.zip'))
        .on('finish', function() {
            console.log('out/' + subdir + '.zip written.');
        });
}